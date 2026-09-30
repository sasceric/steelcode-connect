<?php

namespace App\Tests\Integration;

use App\Controller\Api\SupplierInvoiceController;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SupplierInvoiceTest extends KernelTestCase
{
    public function testThreeWayMatchingPreventsOverbillingAndSupportsVoid(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $tenant = new Tenant('Invoice test');
            $user = new User('invoice-'.bin2hex(random_bytes(6)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $supplier = new Supplier($tenant, 'invoice_supplier', 'Invoice Supplier');
            $warehouse = new Warehouse($tenant, 'invoice_warehouse', 'Invoice Warehouse');
            $product = new Product($tenant);
            $po = new PurchaseOrder($tenant, $supplier, $warehouse, 'EUR', null);
            $po->addItem($product, '10.0000', '10.0000', null);
            foreach ([$tenant, $user, $membership, $supplier, $warehouse, $product, $po] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
            $po->markSent();
            $item = $po->getItems()->first();
            $item->receive('5.0000', '2.0000');
            $po->markReceived();
            $entityManager->flush();

            $tokens = new TokenStorage();
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $services = new Container();
            $services->set('security.token_storage', $tokens);
            $controller = new SupplierInvoiceController();
            $controller->setContainer($services);
            $base = [
                'purchaseOrderId' => $po->getId()->toRfc4122(),
                'invoiceDate' => '2026-09-29',
                'tax' => 0,
                'shipping' => 0,
                'discount' => 0,
                'items' => [[
                    'purchaseOrderItemId' => $item->getId()->toRfc4122(),
                    'quantity' => 3,
                    'unitCost' => 10,
                ]],
                'declaredTotal' => 30,
            ];
            $created = $controller->create($this->jsonRequest($base + ['invoiceNumber' => 'INV-1']), $entityManager);
            self::assertSame(201, $created->getStatusCode(), $created->getContent());
            $firstId = $this->body($created)['invoice']['id'];
            $matched = $controller->match($firstId, $entityManager);
            self::assertSame('matched', $this->body($matched)['invoice']['status']);

            $duplicate = $controller->create($this->jsonRequest($base + ['invoiceNumber' => 'INV-1']), $entityManager);
            self::assertSame(409, $duplicate->getStatusCode());

            $second = $controller->create($this->jsonRequest($base + ['invoiceNumber' => 'INV-2']), $entityManager);
            $secondId = $this->body($second)['invoice']['id'];
            $disputed = $controller->match($secondId, $entityManager);
            self::assertSame('disputed', $this->body($disputed)['invoice']['status']);
            self::assertNotEmpty($this->body($disputed)['invoice']['matchIssues']);

            $corrected = $base;
            $corrected['invoiceNumber'] = 'INV-2';
            $corrected['items'][0]['quantity'] = 2;
            $corrected['declaredTotal'] = 20;
            $updated = $controller->update($secondId, $this->jsonRequest($corrected), $entityManager);
            self::assertSame(200, $updated->getStatusCode(), $updated->getContent());
            $matchedSecond = $controller->match($secondId, $entityManager);
            self::assertSame('matched', $this->body($matchedSecond)['invoice']['status']);

            $wrongCost = $base;
            $wrongCost['invoiceNumber'] = 'INV-3';
            $wrongCost['items'][0]['quantity'] = 1;
            $wrongCost['items'][0]['unitCost'] = 11;
            $wrongCost['declaredTotal'] = 11;
            $costInvoice = $controller->create($this->jsonRequest($wrongCost), $entityManager);
            self::assertSame(201, $costInvoice->getStatusCode());
            $costResult = $controller->match($this->body($costInvoice)['invoice']['id'], $entityManager);
            self::assertSame('disputed', $this->body($costResult)['invoice']['status']);
            self::assertStringContainsString('unit cost', implode(' ', $this->body($costResult)['invoice']['matchIssues']));

            $wrongTotal = $base;
            $wrongTotal['invoiceNumber'] = 'INV-4';
            $wrongTotal['items'][0]['quantity'] = 1;
            $wrongTotal['declaredTotal'] = 15;
            $totalInvoice = $controller->create($this->jsonRequest($wrongTotal), $entityManager);
            self::assertSame(201, $totalInvoice->getStatusCode());
            $totalResult = $controller->match($this->body($totalInvoice)['invoice']['id'], $entityManager);
            self::assertSame('disputed', $this->body($totalResult)['invoice']['status']);
            self::assertStringContainsString('total differs', implode(' ', $this->body($totalResult)['invoice']['matchIssues']));

            $voided = $controller->voidInvoice($firstId, $this->jsonRequest(['reason' => 'Supplier cancelled invoice']), $entityManager);
            self::assertSame('void', $this->body($voided)['invoice']['status']);
            $availability = $controller->availability($po->getId()->toRfc4122(), $entityManager);
            self::assertSame(3, $this->body($availability)['order']['items'][0]['availableToInvoice']);
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    private function jsonRequest(array $data): Request
    {
        return Request::create(
            '/',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR),
        );
    }

    private function body($response): array
    {
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
