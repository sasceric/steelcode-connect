<?php

namespace App\Tests\Integration;

use App\Entity\Product;
use App\Entity\Supplier;
use App\Entity\SupplierOffer;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class PurchasingApiTest extends WebTestCase
{
    public function testDraftPdfReceiptAndDamageFlow(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $tenant = new Tenant('Purchasing API test');
            $user = new User('purchasing-api-'.bin2hex(random_bytes(6)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $warehouse = new Warehouse($tenant, 'default', 'Default warehouse');
            $product = new Product($tenant);
            $supplier = new Supplier($tenant, 'api_supplier', 'API Supplier');
            $supplier->update('API Supplier', 'api_supplier', 'supplier@example.test', null, true);
            $supplier->updateDetails('Purchase team', '1 Supplier Road', '10000', 'Test City', 'BA');
            $offer = new SupplierOffer($tenant, $supplier, $product);
            $offer->update('SUP-1', '10.0000', 'EUR', '1.0000', 2, true, true);
            $offer->setPurchaseUnit('case', 12);
            foreach ([$tenant, $user, $membership, $warehouse, $product, $supplier, $offer] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
            $client->loginUser($user);

            $client->request('POST', '/api/v1/inventory/purchase-orders', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'supplierId' => $supplier->getId()->toRfc4122(),
                'warehouseId' => $warehouse->getId()->toRfc4122(),
                'currency' => 'EUR',
                'items' => [['productId' => $product->getId()->toRfc4122(), 'quantity' => 2]],
            ], JSON_THROW_ON_ERROR));
            self::assertSame(201, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $order = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['order'];
            self::assertSame(12, $order['items'][0]['stockUnitsPerPurchaseUnit']);
            $orderId = $order['id'];

            $client->request('PATCH', '/api/v1/inventory/purchase-orders/'.$orderId, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'supplierId' => $supplier->getId()->toRfc4122(),
                'warehouseId' => $warehouse->getId()->toRfc4122(),
                'currency' => 'EUR',
                'note' => 'Edited draft',
                'items' => [['productId' => $product->getId()->toRfc4122(), 'quantity' => 3]],
            ], JSON_THROW_ON_ERROR));
            self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $edited = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['order'];
            self::assertSame(3.0, $edited['items'][0]['quantity']);

            $client->request('GET', '/api/v1/inventory/purchase-orders/'.$orderId.'/pdf');
            self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            self::assertStringStartsWith('%PDF-', $client->getResponse()->getContent());

            $client->request('POST', '/api/v1/inventory/purchase-orders/'.$orderId.'/send');
            self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $sent = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['order'];
            self::assertSame('supplier@example.test', $sent['supplierSnapshot']['email']);

            $receiptKey = Uuid::v7()->toRfc4122();
            $receiptBody = json_encode([
                'receiptKey' => $receiptKey,
                'items' => [[
                    'itemId' => $sent['items'][0]['id'],
                    'goodQuantity' => 1,
                    'damagedQuantity' => 1,
                ]],
            ], JSON_THROW_ON_ERROR);
            $client->request('POST', '/api/v1/inventory/purchase-orders/'.$orderId.'/receive', server: ['CONTENT_TYPE' => 'application/json'], content: $receiptBody);
            self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $received = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['order'];
            self::assertSame('partially_received', $received['status']);

            $client->request('GET', '/api/v1/inventory/purchase-orders/'.$orderId);
            $detail = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertCount(1, $detail['receipts']);
            self::assertSame('open', $detail['receipts'][0]['damageResolution']);
            $receiptId = $detail['receipts'][0]['id'];

            $client->request('PATCH', '/api/v1/inventory/purchase-orders/'.$orderId.'/receipts/'.$receiptId.'/damage', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'resolution' => 'returned',
                'note' => 'Supplier collected the damaged case',
            ], JSON_THROW_ON_ERROR));
            self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $resolved = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['receipt'];
            self::assertSame('returned', $resolved['damageResolution']);
            self::assertCount(1, $resolved['damageHistory']);
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
