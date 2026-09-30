<?php

namespace App\Tests\Integration;

use App\Controller\Api\PurchasingController;
use App\Entity\Product;
use App\Entity\Supplier;
use App\Entity\SupplierOffer;
use App\Entity\SupplierOfferPrice;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class PurchasingApiTest extends KernelTestCase
{
    public function testControllerFlowWithoutPersistingFixtures(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $tenant = new Tenant('Purchasing controller test');
            $user = new User('purchasing-'.bin2hex(random_bytes(6)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $warehouse = new Warehouse($tenant, 'default', 'Default warehouse');
            $product = new Product($tenant);
            $supplier = new Supplier($tenant, 'test_supplier', 'Test Supplier');
            $supplier->update('Test Supplier', 'test_supplier', 'supplier@example.test', null, true);
            $supplier->updateDetails('Purchasing', '1 Test Road', '10000', 'Test City', 'BA');
            $offer = new SupplierOffer($tenant, $supplier, $product);
            $offer->update('SUP-1', '10.0000', 'EUR', '2.0000', 2, true, true);
            $offer->setPurchaseUnit('case', 12);
            $offer->replacePrices([
                new SupplierOfferPrice($offer, '1.0000', '10.0000', 'EUR', null, null),
                new SupplierOfferPrice($offer, '3.0000', '8.0000', 'EUR', null, null),
            ]);
            foreach ([$tenant, $user, $membership, $warehouse, $product, $supplier, $offer] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $tokenStorage = new TokenStorage();
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $services = new Container();
            $services->set('security.token_storage', $tokenStorage);
            $controller = new PurchasingController(
                new InventoryService(new InventorySyncOutboxService()),
                $entityManager,
            );
            $controller->setContainer($services);

            $baseOrder = [
                'supplierId' => $supplier->getId()->toRfc4122(),
                'warehouseId' => $warehouse->getId()->toRfc4122(),
                'currency' => 'EUR',
                'items' => [['productId' => $product->getId()->toRfc4122(), 'quantity' => 2]],
            ];
            $createdResponse = $controller->createOrder($this->jsonRequest($baseOrder), $entityManager);
            self::assertSame(201, $createdResponse->getStatusCode(), $createdResponse->getContent());
            $created = $this->payload($createdResponse)['order'];
            self::assertSame(12, $created['items'][0]['stockUnitsPerPurchaseUnit']);
            self::assertSame(10, $created['items'][0]['unitCost']);
            $orderId = $created['id'];

            $baseOrder['items'][0]['quantity'] = 3;
            $baseOrder['note'] = 'Edited draft';
            $editedResponse = $controller->updateOrder($orderId, $this->jsonRequest($baseOrder), $entityManager);
            self::assertSame(200, $editedResponse->getStatusCode(), $editedResponse->getContent());
            self::assertEquals(3, $this->payload($editedResponse)['order']['items'][0]['quantity']);
            self::assertSame(8, $this->payload($editedResponse)['order']['items'][0]['unitCost']);

            $pdf = $controller->orderPdf($orderId, $entityManager);
            self::assertSame('application/pdf', $pdf->headers->get('Content-Type'));
            self::assertStringStartsWith('%PDF-', $pdf->getContent());

            $sentResponse = $controller->sendOrder($orderId, $entityManager);
            self::assertSame(200, $sentResponse->getStatusCode(), $sentResponse->getContent());
            $sent = $this->payload($sentResponse)['order'];
            self::assertSame('supplier@example.test', $sent['supplierSnapshot']['email']);

            $previousSender = $_ENV['BREVO_FROM'] ?? null;
            $_ENV['BREVO_FROM'] = 'sender@example.test';
            try {
                $mailer = $this->createMock(MailerInterface::class);
                $mailer->expects(self::once())->method('send');
                $emailResponse = $controller->emailOrder($orderId, $entityManager, $mailer);
                self::assertSame(200, $emailResponse->getStatusCode(), $emailResponse->getContent());
                self::assertSame('supplier@example.test', $this->payload($emailResponse)['order']['lastEmailedTo']);
            } finally {
                if ($previousSender === null) {
                    unset($_ENV['BREVO_FROM']);
                } else {
                    $_ENV['BREVO_FROM'] = $previousSender;
                }
            }

            $receivedResponse = $controller->receiveOrder($orderId, $this->jsonRequest([
                'receiptKey' => Uuid::v7()->toRfc4122(),
                'items' => [[
                    'itemId' => $sent['items'][0]['id'],
                    'goodQuantity' => 1,
                    'damagedQuantity' => 1,
                ]],
            ]), $entityManager);
            self::assertSame(200, $receivedResponse->getStatusCode(), $receivedResponse->getContent());
            self::assertSame('partially_received', $this->payload($receivedResponse)['order']['status']);

            $detail = $this->payload($controller->order($orderId, $entityManager));
            self::assertCount(1, $detail['receipts']);
            self::assertSame('open', $detail['receipts'][0]['damageResolution']);
            self::assertSame('held', $detail['receipts'][0]['quarantineStatus']);
            self::assertSame(12, $detail['receipts'][0]['quarantineQuantity']);
            $receiptId = $detail['receipts'][0]['id'];

            $resolvedResponse = $controller->resolveReceiptDamage($orderId, $receiptId, $this->jsonRequest([
                'resolution' => 'returned',
                'note' => 'Supplier collected the damaged case',
            ]), $entityManager);
            self::assertSame(200, $resolvedResponse->getStatusCode(), $resolvedResponse->getContent());
            $resolved = $this->payload($resolvedResponse)['receipt'];
            self::assertSame('returned', $resolved['damageResolution']);
            self::assertCount(1, $resolved['damageHistory']);

            $disposedResponse = $controller->disposeReceiptQuarantine($orderId, $receiptId, $this->jsonRequest([
                'disposition' => 'returned',
                'note' => 'Supplier collected damaged stock',
            ]), $entityManager);
            self::assertSame(200, $disposedResponse->getStatusCode(), $disposedResponse->getContent());
            self::assertSame('returned', $this->payload($disposedResponse)['receipt']['quarantineStatus']);
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

    private function payload(\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
