<?php

namespace App\Tests\Integration;

use App\Controller\Api\SalesController;
use App\Entity\IntegrationConnection;
use App\Entity\InventoryLevel;
use App\Entity\Product;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Integration\ShopwareSalesMapper;
use App\Integration\ShopwareSalesRecordIngestor;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use App\Service\SalesOrderAllocationService;
use App\Service\SalesOrderIngestionService;
use App\Service\SalesPickListService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ShopwareSalesInventoryLifecycleTest extends KernelTestCase
{
    public function testUnknownSkuStaysVisibleAndCanReserveAfterProductImport(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Pending sales allocation test');
            $connection = new IntegrationConnection(
                $tenant,
                'shopware',
                'Pending Shopware '.bin2hex(random_bytes(4)),
                ['source', 'channel'],
            );
            $connection->activate('Connected');
            $entityManager->persist($tenant);
            $entityManager->persist($connection);
            $entityManager->flush();

            $sku = 'LATE-SKU-'.bin2hex(random_bytes(4));
            $inventory = new InventoryService(new InventorySyncOutboxService());
            $ingestion = new SalesOrderIngestionService(new SalesOrderAllocationService($inventory));
            $records = new ShopwareSalesRecordIngestor(new ShopwareSalesMapper(), $ingestion);
            $order = $records->order(
                $this->orderSource('pending-order', $sku, 2, 'open', 'open'),
                [],
                $connection,
                $entityManager,
                false,
            );
            self::assertSame('new', $order->getStatus());
            self::assertSame([$sku], $order->getUnresolvedSkus());

            $product = new Product($tenant);
            $product->updateIdentity($sku, null);
            $entityManager->persist($product);
            $entityManager->flush();
            $warehouse = $inventory->defaultWarehouse($tenant, $entityManager);
            $inventory->setStock($tenant, $warehouse, $product, '5.0000', null, 'Supplier receipt', $entityManager);
            $entityManager->flush();

            self::assertTrue($ingestion->retryAllocation($order, $entityManager));
            self::assertSame('reserved', $order->getStatus());
            self::assertSame([], $order->getUnresolvedSkus());
            self::assertSame('2.0000', $this->level($warehouse, $product, $entityManager)->getReservedQuantity());
        } finally {
            $database->rollBack();
        }
    }

    public function testPlacedCancelledAndShippedEventsChangeWarehouseStockExactlyOnce(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Live sales lifecycle test');
            $connection = new IntegrationConnection(
                $tenant,
                'shopware',
                'Live Shopware '.bin2hex(random_bytes(4)),
                ['source', 'channel'],
            );
            $connection->activate('Connected');
            $product = new Product($tenant);
            $sku = 'LIVE-SKU-'.bin2hex(random_bytes(4));
            $product->updateIdentity($sku, null);
            $entityManager->persist($tenant);
            $entityManager->persist($connection);
            $entityManager->persist($product);
            $entityManager->flush();

            $inventory = new InventoryService(new InventorySyncOutboxService());
            $warehouse = $inventory->defaultWarehouse($tenant, $entityManager);
            $inventory->setStock($tenant, $warehouse, $product, '10.0000', null, 'Opening balance', $entityManager);
            $entityManager->flush();

            $records = new ShopwareSalesRecordIngestor(
                new ShopwareSalesMapper(),
                new SalesOrderIngestionService(new SalesOrderAllocationService($inventory)),
            );

            $cancelled = $this->orderSource('cancelled-order', $sku, 2, 'open', 'open');
            $placed = $records->order($cancelled, [], $connection, $entityManager, false);
            self::assertSame('reserved', $placed->getStatus());
            self::assertSame('2.0000', $this->level($warehouse, $product, $entityManager)->getReservedQuantity());

            $cancelled['attributes']['stateMachineState']['attributes']['technicalName'] = 'cancelled';
            $records->order($cancelled, [], $connection, $entityManager, false);
            $records->order($cancelled, [], $connection, $entityManager, false);
            self::assertSame('cancelled', $placed->getStatus());
            self::assertSame('0.0000', $this->level($warehouse, $product, $entityManager)->getReservedQuantity());
            self::assertSame('10.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());

            $shipped = $this->orderSource('shipped-order', $sku, 2, 'open', 'shipped');
            $fulfilled = $records->order($shipped, [], $connection, $entityManager, false);
            $records->order($shipped, [], $connection, $entityManager, false);
            self::assertSame('fulfilled', $fulfilled->getStatus());
            self::assertSame('0.0000', $this->level($warehouse, $product, $entityManager)->getReservedQuantity());
            self::assertSame('8.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());

            $duplicateSku = $this->orderSource('duplicate-sku-order', $sku, 1, 'open', 'shipped');
            $duplicateSku['attributes']['lineItems'][] = [
                'id' => 'duplicate-sku-other-line',
                'attributes' => [
                    'type' => 'product',
                    'label' => 'Same SKU again',
                    'quantity' => 1,
                    'payload' => ['productNumber' => $sku],
                ],
            ];
            $records->order($duplicateSku, [], $connection, $entityManager, false);
            self::assertSame('0.0000', $this->level($warehouse, $product, $entityManager)->getReservedQuantity());
            self::assertSame('6.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());
        } finally {
            $database->rollBack();
        }
    }

    public function testPartialDeliveriesAndEditedLinesKeepReservationsAndShipmentIdempotent(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Partial delivery test');
            $connection = new IntegrationConnection($tenant, 'shopware', 'Partial Shopware '.bin2hex(random_bytes(4)), ['source', 'channel']);
            $connection->activate('Connected');
            $first = new Product($tenant);
            $firstSku = 'PART-A-'.bin2hex(random_bytes(4));
            $first->updateIdentity($firstSku, null);
            $second = new Product($tenant);
            $secondSku = 'PART-B-'.bin2hex(random_bytes(4));
            $second->updateIdentity($secondSku, null);
            $entityManager->persist($tenant);
            $entityManager->persist($connection);
            $entityManager->persist($first);
            $entityManager->persist($second);
            $entityManager->flush();

            $inventory = new InventoryService(new InventorySyncOutboxService());
            $warehouse = $inventory->defaultWarehouse($tenant, $entityManager);
            $inventory->setStock($tenant, $warehouse, $first, '10.0000', null, 'Opening balance', $entityManager);
            $inventory->setStock($tenant, $warehouse, $second, '10.0000', null, 'Opening balance', $entityManager);
            $entityManager->flush();

            $records = new ShopwareSalesRecordIngestor(
                new ShopwareSalesMapper(),
                new SalesOrderIngestionService(new SalesOrderAllocationService($inventory)),
            );
            $source = $this->orderSource('partial-order', $firstSku, 4, 'open', 'shipped');
            $source['attributes']['deliveries'][0]['attributes']['positions'] = [[
                'attributes' => ['orderLineItemId' => 'partial-order-line', 'quantity' => 1],
            ]];
            $source['attributes']['deliveries'][] = [
                'id' => 'partial-order-second-delivery',
                'attributes' => [
                    'stateMachineState' => ['attributes' => ['technicalName' => 'open']],
                    'positions' => [[
                        'attributes' => ['orderLineItemId' => 'partial-order-line', 'quantity' => 3],
                    ]],
                ],
            ];

            $order = $records->order($source, [], $connection, $entityManager, false);
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('partially_fulfilled', $order->getStatus());
            self::assertSame('1.0000', $this->item($order, 'partial-order-line')->getFulfilledQuantity());
            self::assertSame('3.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());
            self::assertSame('9.0000', $this->level($warehouse, $first, $entityManager)->getQuantity());

            $source['attributes']['lineItems'][0]['attributes']['quantity'] = 5;
            $source['attributes']['lineItems'][] = [
                'id' => 'partial-order-added-line',
                'attributes' => [
                    'type' => 'product',
                    'label' => 'Added item',
                    'quantity' => 2,
                    'payload' => ['productNumber' => $secondSku],
                ],
            ];
            $source['attributes']['deliveries'][1]['attributes']['positions'] = [
                ['attributes' => ['orderLineItemId' => 'partial-order-line', 'quantity' => 4]],
                ['attributes' => ['orderLineItemId' => 'partial-order-added-line', 'quantity' => 2]],
            ];

            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('partially_fulfilled', $order->getStatus());
            self::assertSame('4.0000', $this->item($order, 'partial-order-line')->getReservedQuantity());
            self::assertSame('4.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());
            self::assertSame('2.0000', $this->level($warehouse, $second, $entityManager)->getReservedQuantity());

            $source['attributes']['deliveries'][1]['attributes']['stateMachineState']['attributes']['technicalName'] = 'shipped';
            $records->order($source, [], $connection, $entityManager, false);
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('fulfilled', $order->getStatus());
            self::assertSame('0.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());
            self::assertSame('0.0000', $this->level($warehouse, $second, $entityManager)->getReservedQuantity());
            self::assertSame('5.0000', $this->level($warehouse, $first, $entityManager)->getQuantity());
            self::assertSame('8.0000', $this->level($warehouse, $second, $entityManager)->getQuantity());

            $source['attributes']['lineItems'][0]['attributes']['quantity'] = 4;
            try {
                $records->order($source, [], $connection, $entityManager, false);
                self::fail('Reducing a shipped line below its fulfilled quantity must fail.');
            } catch (\DomainException) {
                self::assertSame('5.0000', $this->level($warehouse, $first, $entityManager)->getQuantity());
            }
        } finally {
            $database->rollBack();
        }
    }

    public function testUnshippedLineReplacementRemovalAndInsufficientStockStayConsistent(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Order line edit test');
            $connection = new IntegrationConnection($tenant, 'shopware', 'Edit Shopware '.bin2hex(random_bytes(4)), ['source', 'channel']);
            $connection->activate('Connected');
            $first = new Product($tenant);
            $firstSku = 'EDIT-A-'.bin2hex(random_bytes(4));
            $first->updateIdentity($firstSku, null);
            $second = new Product($tenant);
            $secondSku = 'EDIT-B-'.bin2hex(random_bytes(4));
            $second->updateIdentity($secondSku, null);
            $entityManager->persist($tenant);
            $entityManager->persist($connection);
            $entityManager->persist($first);
            $entityManager->persist($second);
            $entityManager->flush();

            $inventory = new InventoryService(new InventorySyncOutboxService());
            $warehouse = $inventory->defaultWarehouse($tenant, $entityManager);
            $inventory->setStock($tenant, $warehouse, $first, '10.0000', null, 'Opening balance', $entityManager);
            $inventory->setStock($tenant, $warehouse, $second, '10.0000', null, 'Opening balance', $entityManager);
            $entityManager->flush();
            $records = new ShopwareSalesRecordIngestor(
                new ShopwareSalesMapper(),
                new SalesOrderIngestionService(new SalesOrderAllocationService($inventory)),
            );

            $source = $this->orderSource('edited-order', $firstSku, 2, 'open', 'open');
            $source['attributes']['lineItems'][] = [
                'id' => 'edited-order-second-line',
                'attributes' => [
                    'type' => 'product',
                    'label' => 'Same product twice',
                    'quantity' => 2,
                    'payload' => ['productNumber' => $firstSku],
                ],
            ];
            $order = $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('4.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());

            $source['attributes']['lineItems'][0]['attributes']['quantity'] = 1;
            $source['attributes']['lineItems'][1]['attributes']['quantity'] = 3;
            $source['attributes']['lineItems'][1]['attributes']['payload']['productNumber'] = $secondSku;
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('1.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());
            self::assertSame('3.0000', $this->level($warehouse, $second, $entityManager)->getReservedQuantity());

            array_pop($source['attributes']['lineItems']);
            $records->order($source, [], $connection, $entityManager, false);
            self::assertCount(1, $order->getItems());
            self::assertSame('1.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());
            self::assertSame('0.0000', $this->level($warehouse, $second, $entityManager)->getReservedQuantity());

            $source['attributes']['lineItems'][0]['attributes']['quantity'] = 100;
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('new', $order->getStatus());
            self::assertSame('0.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());
            self::assertSame('10.0000', $this->level($warehouse, $first, $entityManager)->getQuantity());

            $source['attributes']['lineItems'][0]['attributes']['quantity'] = 1;
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('reserved', $order->getStatus());
            self::assertSame('1.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());

            $source['attributes']['stateMachineState']['attributes']['technicalName'] = 'cancelled';
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('cancelled', $order->getStatus());
            self::assertSame('0.0000', $this->level($warehouse, $first, $entityManager)->getReservedQuantity());
        } finally {
            $database->rollBack();
        }
    }

    public function testPartialCancellationReleasesOnlyUnshippedStockAndAmbiguousStateStopsSafely(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Partial cancellation test');
            $connection = new IntegrationConnection($tenant, 'shopware', 'Cancel Shopware '.bin2hex(random_bytes(4)), ['source', 'channel']);
            $connection->activate('Connected');
            $user = new User('shipment-'.bin2hex(random_bytes(5)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $product = new Product($tenant);
            $sku = 'CANCEL-PART-'.bin2hex(random_bytes(4));
            $product->updateIdentity($sku, null);
            $entityManager->persist($tenant);
            $entityManager->persist($connection);
            $entityManager->persist($user);
            $entityManager->persist($membership);
            $entityManager->persist($product);
            $entityManager->flush();

            $inventory = new InventoryService(new InventorySyncOutboxService());
            $warehouse = $inventory->defaultWarehouse($tenant, $entityManager);
            $level = $inventory->setStock($tenant, $warehouse, $product, '10.0000', null, 'Opening balance', $entityManager);
            $entityManager->flush();
            $ingestion = new SalesOrderIngestionService(new SalesOrderAllocationService($inventory));
            $records = new ShopwareSalesRecordIngestor(new ShopwareSalesMapper(), $ingestion);

            $source = $this->orderSource('partial-cancel', $sku, 2, 'open', 'shipped');
            $source['attributes']['deliveries'][0]['attributes']['positions'] = [[
                'attributes' => ['orderLineItemId' => 'partial-cancel-line', 'quantity' => 1],
            ]];
            $source['attributes']['deliveries'][] = [
                'id' => 'partial-cancel-pending',
                'attributes' => ['stateMachineState' => ['attributes' => ['technicalName' => 'open']]],
            ];
            $order = $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('partially_fulfilled', $order->getStatus());
            self::assertSame('1.0000', $level->getReservedQuantity());

            $source['attributes']['stateMachineState']['attributes']['technicalName'] = 'cancelled';
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('cancelled', $order->getStatus());
            self::assertSame('0.0000', $level->getReservedQuantity());
            self::assertSame('9.0000', $level->getQuantity());

            $ambiguous = $this->orderSource('ambiguous-partial', $sku, 2, 'open', 'shipped_partially');
            $ambiguousOrder = $records->order($ambiguous, [], $connection, $entityManager, false);
            self::assertTrue($ambiguousOrder->needsShipmentReconciliation());
            self::assertSame('reserved', $ambiguousOrder->getStatus());
            self::assertSame('9.0000', $level->getQuantity());
            self::assertSame('2.0000', $level->getReservedQuantity());

            $tokens = new TokenStorage();
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $services = new Container();
            $services->set('security.token_storage', $tokens);
            $controller = new SalesController();
            $controller->setContainer($services);
            $pickListResponse = $controller->picklists(
                Request::create('/api/v1/sales/picklists', 'GET'),
                $entityManager,
                new SalesPickListService(),
            );
            self::assertSame(200, $pickListResponse->getStatusCode());
            $pickListPayload = json_decode($pickListResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('ambiguous-partial', $pickListPayload['picklists'][0]['number']);
            self::assertSame(1, $pickListPayload['picklists'][0]['lineCount']);
            $response = $controller->reconcileShipment(
                $ambiguousOrder->getId()->toRfc4122(),
                Request::create(
                    '/api/v1/sales/orders/'.$ambiguousOrder->getId()->toRfc4122().'/shipment-reconciliation',
                    'POST',
                    [],
                    [],
                    [],
                    ['CONTENT_TYPE' => 'application/json'],
                    json_encode(['targets' => ['ambiguous-partial-line' => '1']], JSON_THROW_ON_ERROR),
                ),
                $entityManager,
                $ingestion,
            );
            self::assertSame(200, $response->getStatusCode());
            $records->order($ambiguous, [], $connection, $entityManager, false);
            self::assertSame('partially_fulfilled', $ambiguousOrder->getStatus());
            self::assertSame('8.0000', $level->getQuantity());
            self::assertSame('1.0000', $level->getReservedQuantity());

            $ingestion->reconcileManualShipment(
                $ambiguousOrder,
                ['ambiguous-partial-line' => '2'],
                $entityManager,
            );
            $records->order($ambiguous, [], $connection, $entityManager, false);
            self::assertSame('fulfilled', $ambiguousOrder->getStatus());
            self::assertSame('7.0000', $level->getQuantity());
            self::assertSame('0.0000', $level->getReservedQuantity());

            $missingPositions = $this->orderSource('missing-positions', $sku, 2, 'open', 'shipped');
            $missingPositions['attributes']['deliveries'][] = [
                'id' => 'missing-positions-open-delivery',
                'attributes' => ['stateMachineState' => ['attributes' => ['technicalName' => 'open']]],
            ];
            $missingOrder = $records->order($missingPositions, [], $connection, $entityManager, false);
            self::assertTrue($missingOrder->needsShipmentReconciliation());
            self::assertSame('7.0000', $level->getQuantity());
            self::assertSame('2.0000', $level->getReservedQuantity());
            $ingestion->reconcileManualShipment(
                $missingOrder,
                ['missing-positions-line' => '1'],
                $entityManager,
            );
            self::assertSame('6.0000', $level->getQuantity());
            self::assertSame('1.0000', $level->getReservedQuantity());
        } finally {
            $database->rollBack();
        }
    }

    /** @return array<string, mixed> */
    private function orderSource(
        string $id,
        string $sku,
        int $quantity,
        string $orderState,
        string $deliveryState,
    ): array {
        return [
            'id' => $id,
            'attributes' => [
                'orderNumber' => $id,
                'orderDateTime' => '2026-09-29T12:00:00+00:00',
                'lineItems' => [[
                    'id' => $id.'-line',
                    'attributes' => [
                        'type' => 'product',
                        'label' => 'Test item',
                        'quantity' => $quantity,
                        'payload' => ['productNumber' => $sku],
                    ],
                ]],
                'stateMachineState' => ['attributes' => ['technicalName' => $orderState]],
                'deliveries' => [[
                    'id' => $id.'-delivery',
                    'attributes' => [
                        'stateMachineState' => ['attributes' => ['technicalName' => $deliveryState]],
                    ],
                ]],
            ],
        ];
    }

    private function level(
        \App\Entity\Warehouse $warehouse,
        Product $product,
        \Doctrine\ORM\EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
        self::assertInstanceOf(InventoryLevel::class, $level);

        return $level;
    }

    private function item(\App\Entity\SalesOrder $order, string $externalLineId): \App\Entity\SalesOrderItem
    {
        foreach ($order->getItems() as $item) {
            if ($item->getExternalLineId() === $externalLineId) {
                return $item;
            }
        }

        self::fail(sprintf('Order line %s was not found.', $externalLineId));
    }
}
