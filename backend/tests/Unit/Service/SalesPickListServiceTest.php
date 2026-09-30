<?php

namespace App\Tests\Unit\Service;

use App\Entity\IntegrationConnection;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Entity\Warehouse;
use App\Service\SalesPickListService;
use PHPUnit\Framework\TestCase;

final class SalesPickListServiceTest extends TestCase
{
    public function testOnlyOutstandingReservationsAppearOnThePickList(): void
    {
        $tenant = new Tenant('Pick test');
        $connection = new IntegrationConnection($tenant, 'shopware', 'Shop', ['source', 'channel']);
        $warehouse = new Warehouse($tenant, 'MAIN', 'Main warehouse');
        $order = new SalesOrder($tenant, $connection, 'external-1', '10001');
        $first = $order->addItem(null, 'line-a', 'SKU-A', 'First product', '3.0000');
        $firstAllocation = $first->addAllocation($warehouse, '3.0000');
        $second = $order->addItem(null, 'line-b', 'SKU-B', 'Second product', '2.0000');
        $second->addAllocation($warehouse, '2.0000');
        $order->markReserved();

        $pickLists = new SalesPickListService();
        self::assertCount(2, $pickLists->lines($order));

        $shipped = $firstAllocation->splitForShipment('1.0000');
        self::assertNotNull($shipped);
        $first->fulfill('1.0000');
        $first->addSplitAllocation($shipped);
        $order->markPartiallyFulfilled();

        $lines = $pickLists->lines($order);
        self::assertSame('2.0000', $lines[0]['quantity']);
        self::assertSame('2.0000', $lines[1]['quantity']);
        self::assertSame('MAIN', $lines[0]['warehouseCode']);

        $order->markCancelled();
        self::assertSame([], $pickLists->lines($order));
    }

    public function testHistoricalOrdersAreNeverPickable(): void
    {
        $tenant = new Tenant('Historical pick test');
        $connection = new IntegrationConnection($tenant, 'shopware', 'Shop', ['source', 'channel']);
        $order = new SalesOrder($tenant, $connection, 'external-2', '10002');
        $order->addItem(null, 'line-a', 'SKU-A', 'Product', '1.0000');
        $order->markHistorical();

        self::assertSame([], (new SalesPickListService())->lines($order));
    }
}
