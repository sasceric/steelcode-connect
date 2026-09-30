<?php

namespace App\Tests\Integration;

use App\Controller\Api\PurchasingController;
use App\Controller\Api\ReferenceDataController;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\Supplier;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\Unit;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Entity\PurchaseOrderItem;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SupplierOfferBatchTest extends KernelTestCase
{
    public function testCreatesMultipleOffersWithCatalogCurrencyAndUnit(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $tenant = new Tenant('Supplier offer batch test');
            $user = new User('offers-'.bin2hex(random_bytes(6)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $supplier = new Supplier($tenant, 'batch_supplier', 'Batch supplier');
            $warehouse = new Warehouse($tenant, 'default', 'Default warehouse');
            $first = new Product($tenant);
            $second = new Product($tenant);
            $third = new Product($tenant);
            $unit = new Unit($tenant, 'case', 'cs', ['en-GB' => 'Case']);
            $currency = $entityManager->getRepository(Currency::class)->findOneBy(['code' => 'EUR']);
            if (!$currency instanceof Currency) {
                $currency = new Currency('EUR', '€', 2);
                $entityManager->persist($currency);
            }
            foreach ([$tenant, $user, $membership, $supplier, $warehouse, $first, $second, $third, $unit] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $tokens = new TokenStorage();
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $services = new Container();
            $services->set('security.token_storage', $tokens);
            $controller = new PurchasingController(
                new InventoryService(new InventorySyncOutboxService()),
                $entityManager,
            );
            $controller->setContainer($services);

            $row = [
                'minimumOrderQuantity' => 2,
                'prices' => [
                    ['minimumQuantity' => 1, 'unitCost' => 10, 'currency' => 'EUR'],
                    ['minimumQuantity' => 10, 'unitCost' => 8, 'currency' => 'EUR'],
                ],
                'currency' => 'EUR',
                'purchaseUnit' => 'case',
                'stockUnitsPerPurchaseUnit' => 0.75,
                'leadTimeDays' => 5,
            ];
            $request = $this->request([
                'supplierId' => $supplier->getId()->toRfc4122(),
                'offers' => [
                    $row + ['productId' => $first->getId()->toRfc4122()],
                    $row + ['productId' => $second->getId()->toRfc4122()],
                ],
            ]);
            $result = $controller->createOfferBatch($request, $entityManager);
            self::assertSame(201, $result->getStatusCode(), $result->getContent());
            $saved = json_decode($result->getContent(), true, 512, JSON_THROW_ON_ERROR)['offers'];
            self::assertCount(2, $saved);
            self::assertSame(2, $saved[0]['minimumOrderQuantity']);
            self::assertSame(0.75, $saved[0]['stockUnitsPerPurchaseUnit']);
            self::assertCount(2, $saved[0]['prices']);
            $orderRequest = [
                'supplierId' => $supplier->getId()->toRfc4122(),
                'warehouseId' => $warehouse->getId()->toRfc4122(),
                'currency' => 'EUR',
                'items' => [['productId' => $first->getId()->toRfc4122(), 'quantity' => 1]],
            ];
            $belowMinimum = $controller->createOrder($this->request($orderRequest), $entityManager);
            self::assertSame(422, $belowMinimum->getStatusCode());
            $orderRequest['items'][0]['quantity'] = 2;
            $createdOrder = $controller->createOrder($this->request($orderRequest), $entityManager);
            self::assertSame(201, $createdOrder->getStatusCode(), $createdOrder->getContent());
            $order = json_decode($createdOrder->getContent(), true, 512, JSON_THROW_ON_ERROR)['order'];
            self::assertSame(10, $order['items'][0]['unitCost']);
            self::assertSame(0.75, $order['items'][0]['stockUnitsPerPurchaseUnit']);
            $orderRequest['items'][0]['quantity'] = 10;
            $repriced = $controller->updateOrder($order['id'], $this->request($orderRequest), $entityManager);
            self::assertSame(200, $repriced->getStatusCode(), $repriced->getContent());
            $repricedOrder = json_decode($repriced->getContent(), true, 512, JSON_THROW_ON_ERROR)['order'];
            self::assertSame(8, $repricedOrder['items'][0]['unitCost']);
            $offerUpdate = $controller->updateOffer($saved[0]['id'], $this->request([
                'supplierSku' => 'SUP-UPDATED',
                'minimumOrderQuantity' => 2,
                'preferredCurrency' => 'EUR',
                'purchaseUnit' => 'case',
                'stockUnitsPerPurchaseUnit' => 0.75,
                'prices' => [
                    ['minimumQuantity' => 1, 'unitCost' => 12, 'currency' => 'EUR'],
                    ['minimumQuantity' => 10, 'unitCost' => 9, 'currency' => 'EUR'],
                ],
            ]), $entityManager);
            self::assertSame(200, $offerUpdate->getStatusCode(), $offerUpdate->getContent());
            self::assertCount(2, json_decode($offerUpdate->getContent(), true, 512, JSON_THROW_ON_ERROR)['offer']['prices']);
            $unchangedDraft = $controller->order($order['id'], $entityManager);
            self::assertSame(8, json_decode($unchangedDraft->getContent(), true, 512, JSON_THROW_ON_ERROR)['order']['items'][0]['unitCost']);
            $updatedDraft = $controller->updateOrder($order['id'], $this->request($orderRequest), $entityManager);
            self::assertSame(9, json_decode($updatedDraft->getContent(), true, 512, JSON_THROW_ON_ERROR)['order']['items'][0]['unitCost']);
            $updatedLine = json_decode($updatedDraft->getContent(), true, 512, JSON_THROW_ON_ERROR)['order']['items'][0];
            $item = $entityManager->getRepository(PurchaseOrderItem::class)->findOneBy(['id' => $updatedLine['id']]);
            self::assertInstanceOf(PurchaseOrderItem::class, $item);
            self::assertSame('1.5000', $item->toStockQuantity('2.0000'));
            try {
                $item->toStockQuantity('0.0001');
                self::fail('A sub-precision stock conversion must not be rounded silently.');
            } catch (\DomainException) {
                self::assertTrue(true);
            }
            $duplicate = $controller->createOfferBatch($request, $entityManager);
            self::assertSame(409, $duplicate->getStatusCode());
            $references = new ReferenceDataController();
            $references->setContainer($services);
            $deleteUnit = $references->deleteUnit($unit->getId()->toRfc4122(), $entityManager);
            self::assertSame(409, $deleteUnit->getStatusCode());
            $invalid = $controller->createOfferBatch($this->request([
                'supplierId' => $supplier->getId()->toRfc4122(),
                'offers' => [array_merge($row, ['productId' => $third->getId()->toRfc4122(), 'purchaseUnit' => 'unknown'])],
            ]), $entityManager);
            self::assertSame(422, $invalid->getStatusCode());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    private function request(array $data): Request
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
}
