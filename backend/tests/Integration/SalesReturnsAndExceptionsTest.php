<?php

namespace App\Tests\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\InventoryLevel;
use App\Entity\InventorySyncOutbox;
use App\Entity\Product;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Integration\ShopwareSalesMapper;
use App\Integration\ShopwareSalesRecordIngestor;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use App\Service\OperationsExceptionService;
use App\Service\SalesOrderAllocationService;
use App\Service\SalesOrderIngestionService;
use App\Service\SalesPickListService;
use App\Service\SalesPickTaskService;
use App\Service\SalesReturnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class SalesReturnsAndExceptionsTest extends KernelTestCase
{
    public function testShopwareOrderPickPartialShipmentReturnAndFinalShipment(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            [$tenant, $user, $connection, $product, $warehouse, $inventory] = $this->fixture($entityManager);
            $records = $this->records($inventory);
            $source = $this->orderSource('return-flow', $product->getSku(), 3);
            $order = $records->order($source, [], $connection, $entityManager, false);
            $item = $order->getItems()->first();
            self::assertNotFalse($item);
            self::assertSame('reserved', $order->getStatus());
            self::assertSame('3.0000', $this->level($warehouse, $product, $entityManager)->getReservedQuantity());

            $picking = new SalesPickTaskService(new SalesPickListService());
            $task = $picking->start($order, $user, $entityManager);
            $task = $picking->record(
                $order,
                $task->getId(),
                [$item->getId()->toRfc4122() => '3'],
                $task->getVersion(),
                true,
                $user,
                $entityManager,
            );
            self::assertSame('picked', $task->getStatus());
            self::assertSame('10.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());

            $source['attributes']['deliveries'][0]['attributes']['stateMachineState']['attributes']['technicalName'] = 'shipped';
            $source['attributes']['deliveries'][0]['attributes']['positions'] = [[
                'attributes' => ['orderLineItemId' => 'return-flow-line', 'quantity' => 1],
            ]];
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('partially_fulfilled', $order->getStatus());
            self::assertSame('9.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());
            self::assertSame('2.0000', $this->level($warehouse, $product, $entityManager)->getReservedQuantity());

            $returns = new SalesReturnService($inventory);
            $requestId = Uuid::v7();
            $record = $returns->receive(
                $order,
                $requestId,
                $item->getId(),
                $warehouse->getId(),
                '1.0000',
                'customer_return',
                'unknown',
                'quarantine',
                'Needs inspection',
                $user,
                $entityManager,
            );
            self::assertSame('10.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());
            self::assertSame('1.0000', $this->level($warehouse, $product, $entityManager)->getUnavailableQuantity());
            self::assertSame('7.0000', $this->level($warehouse, $product, $entityManager)->getAvailableQuantity());
            self::assertSame('0.0000', $returns->options($order, $entityManager)[0]['remaining']);
            $exceptions = new OperationsExceptionService();
            self::assertSame(1, $exceptions->list(
                $tenant,
                'quarantined_returns',
                1,
                25,
                $entityManager,
            )['pagination']['total']);

            try {
                $inventory->disposeQuarantine(
                    $tenant,
                    $warehouse,
                    $product,
                    '1.0000',
                    'released',
                    $user,
                    'supplier-receipt-test',
                    null,
                    $entityManager,
                );
                self::fail('Supplier disposition must not consume customer-return quarantine.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('Supplier quarantine stock', $exception->getMessage());
            }

            self::assertSame($record->getId(), $returns->receive(
                $order,
                $requestId,
                $item->getId(),
                $warehouse->getId(),
                '1.0000',
                'customer_return',
                'unknown',
                'quarantine',
                'Needs inspection',
                $user,
                $entityManager,
            )->getId());
            $returns->resolve($order, $record->getId(), 'restock', 'sealed', $user, $entityManager);
            $returns->resolve($order, $record->getId(), 'restock', 'sealed', $user, $entityManager);
            self::assertSame('10.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());
            self::assertSame('0.0000', $this->level($warehouse, $product, $entityManager)->getUnavailableQuantity());
            self::assertSame('8.0000', $this->level($warehouse, $product, $entityManager)->getAvailableQuantity());
            self::assertSame(0, $exceptions->list(
                $tenant,
                'quarantined_returns',
                1,
                25,
                $entityManager,
            )['pagination']['total']);

            try {
                $returns->receive(
                    $order,
                    Uuid::v7(),
                    $item->getId(),
                    $warehouse->getId(),
                    '1.0000',
                    'other',
                    'sealed',
                    'restock',
                    null,
                    $user,
                    $entityManager,
                );
                self::fail('A second return cannot exceed the one shipped unit.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('exceeds the shipped', $exception->getMessage());
            }

            $source['attributes']['deliveries'][0]['attributes']['positions'][0]['attributes']['quantity'] = 3;
            $records->order($source, [], $connection, $entityManager, false);
            $records->order($source, [], $connection, $entityManager, false);
            self::assertSame('fulfilled', $order->getStatus());
            self::assertSame('8.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());
            self::assertSame('0.0000', $this->level($warehouse, $product, $entityManager)->getReservedQuantity());
            self::assertSame('2.0000', $returns->options($order, $entityManager)[0]['remaining']);

            $writtenOff = $returns->receive(
                $order,
                Uuid::v7(),
                $item->getId(),
                $warehouse->getId(),
                '1.0000',
                'damaged',
                'unknown',
                'quarantine',
                null,
                $user,
                $entityManager,
            );
            self::assertSame('9.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());
            self::assertSame('1.0000', $this->level($warehouse, $product, $entityManager)->getUnavailableQuantity());
            $returns->resolve($order, $writtenOff->getId(), 'write_off', 'damaged', $user, $entityManager);
            self::assertSame('8.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());
            self::assertSame('0.0000', $this->level($warehouse, $product, $entityManager)->getUnavailableQuantity());

            try {
                $returns->receive(
                    $order,
                    Uuid::v7(),
                    $item->getId(),
                    $warehouse->getId(),
                    '1.0000',
                    'damaged',
                    'damaged',
                    'restock',
                    null,
                    $user,
                    $entityManager,
                );
                self::fail('Damaged goods must not become sellable stock.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('cannot be restocked', $exception->getMessage());
            }

            $returns->receive(
                $order,
                Uuid::v7(),
                $item->getId(),
                $warehouse->getId(),
                '1.0000',
                'other',
                'sealed',
                'restock',
                null,
                $user,
                $entityManager,
            );
            self::assertSame('9.0000', $this->level($warehouse, $product, $entityManager)->getQuantity());
            self::assertSame('0.0000', $returns->options($order, $entityManager)[0]['remaining']);
        } finally {
            $database->rollBack();
        }
    }

    public function testExceptionWorkbenchFindsRealIssuesWithoutCrossTenantLeakage(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            [$tenant, $user, $connection, $product, $warehouse, $inventory] = $this->fixture($entityManager);
            $records = $this->records($inventory);
            $unmatched = $records->order(
                $this->orderSource('unmatched-order', 'MISSING-'.bin2hex(random_bytes(4)), 1),
                [],
                $connection,
                $entityManager,
                false,
            );
            $inventory->setStock($tenant, $warehouse, $product, '0.0000', $user, 'Depleted', $entityManager);
            $unallocated = $records->order(
                $this->orderSource('unallocated-order', $product->getSku(), 1),
                [],
                $connection,
                $entityManager,
                false,
            );
            $event = $entityManager->getRepository(InventorySyncOutbox::class)->findOneBy([
                'tenant' => $tenant,
                'product' => $product,
            ]);
            self::assertInstanceOf(InventorySyncOutbox::class, $event);
            $event->markFailed('Test connector error');
            $run = new IntegrationImportRun($tenant, $connection, 'sales');
            $run->fail('Test import error');
            $entityManager->persist($run);
            $cursor = new IntegrationSalesSyncCursor($connection, new \DateTimeImmutable('-1 day'));
            $cursor->fail('Test incremental sync error');
            $entityManager->persist($cursor);
            $entityManager->flush();

            $exceptions = new OperationsExceptionService();
            $unmatchedIssues = $exceptions->list($tenant, 'unmatched_products', 1, 25, $entityManager);
            self::assertSame(1, $unmatchedIssues['pagination']['total']);
            self::assertSame($unmatched->getId()->toRfc4122(), $unmatchedIssues['issues'][0]['targetId']);
            $stockIssues = $exceptions->list($tenant, 'unallocated_orders', 1, 25, $entityManager);
            self::assertSame($unallocated->getId()->toRfc4122(), $stockIssues['issues'][0]['targetId']);
            self::assertSame('Test connector error', $exceptions->list(
                $tenant,
                'stock_sync_failed',
                1,
                25,
                $entityManager,
            )['issues'][0]['detail']);
            self::assertSame('Test import error', $exceptions->list(
                $tenant,
                'import_failed',
                1,
                25,
                $entityManager,
            )['issues'][0]['detail']);
            self::assertSame('Test incremental sync error', $exceptions->list(
                $tenant,
                'sales_sync_failed',
                1,
                25,
                $entityManager,
            )['issues'][0]['detail']);

            $successfulRun = new IntegrationImportRun($tenant, $connection, 'sales');
            $successfulRun->start(0);
            $successfulRun->complete();
            $entityManager->persist($successfulRun);
            $entityManager->flush();
            self::assertSame(0, $exceptions->list(
                $tenant,
                'import_failed',
                1,
                25,
                $entityManager,
            )['pagination']['total']);

            $otherTenant = new Tenant('Isolated '.bin2hex(random_bytes(4)));
            $entityManager->persist($otherTenant);
            $entityManager->flush();
            self::assertSame(0, $exceptions->list(
                $otherTenant,
                'unmatched_products',
                1,
                25,
                $entityManager,
            )['pagination']['total']);
        } finally {
            $database->rollBack();
        }
    }

    /** @return array{Tenant, User, IntegrationConnection, Product, \App\Entity\Warehouse, InventoryService} */
    private function fixture(EntityManagerInterface $entityManager): array
    {
        $tenant = new Tenant('Sales acceptance '.bin2hex(random_bytes(4)));
        $user = new User('sales-'.bin2hex(random_bytes(4)).'@example.invalid');
        $user->setPassword('test-password-hash');
        $membership = new TenantMembership($tenant, $user, 'owner');
        $connection = new IntegrationConnection($tenant, 'shopware', 'Shopware', ['source', 'channel']);
        $connection->activate('Connected');
        $product = new Product($tenant);
        $product->updateIdentity('RETURN-'.bin2hex(random_bytes(4)), null);
        $entityManager->persist($tenant);
        $entityManager->persist($user);
        $entityManager->persist($membership);
        $entityManager->persist($connection);
        $entityManager->persist($product);
        $entityManager->flush();

        $inventory = new InventoryService(new InventorySyncOutboxService());
        $warehouse = $inventory->defaultWarehouse($tenant, $entityManager);
        $inventory->setStock($tenant, $warehouse, $product, '10.0000', $user, 'Opening balance', $entityManager);
        $entityManager->flush();

        return [$tenant, $user, $connection, $product, $warehouse, $inventory];
    }

    private function records(InventoryService $inventory): ShopwareSalesRecordIngestor
    {
        return new ShopwareSalesRecordIngestor(
            new ShopwareSalesMapper(),
            new SalesOrderIngestionService(new SalesOrderAllocationService($inventory)),
        );
    }

    /** @return array<string, mixed> */
    private function orderSource(string $id, string $sku, int $quantity): array
    {
        return [
            'id' => $id,
            'attributes' => [
                'orderNumber' => $id,
                'orderDateTime' => '2026-09-29T12:00:00+00:00',
                'lineItems' => [[
                    'id' => $id.'-line',
                    'attributes' => [
                        'type' => 'product',
                        'label' => 'Test return product',
                        'quantity' => $quantity,
                        'payload' => ['productNumber' => $sku],
                    ],
                ]],
                'stateMachineState' => ['attributes' => ['technicalName' => 'open']],
                'deliveries' => [[
                    'id' => $id.'-delivery',
                    'attributes' => [
                        'stateMachineState' => ['attributes' => ['technicalName' => 'open']],
                    ],
                ]],
            ],
        ];
    }

    private function level(
        \App\Entity\Warehouse $warehouse,
        Product $product,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
        self::assertInstanceOf(InventoryLevel::class, $level);

        return $level;
    }
}
