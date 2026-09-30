<?php

namespace App\Tests\Unit\Entity;

use App\Entity\IntegrationConnection;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use PHPUnit\Framework\TestCase;

final class SalesOrderHistoricalTest extends TestCase
{
    public function testHistoricalOrderKeepsUnmatchedLineAndSourceState(): void
    {
        $tenant = new Tenant('Example');
        $connection = new IntegrationConnection(
            $tenant,
            'shopware',
            'Test shop',
            ['source', 'channel'],
        );
        $order = new SalesOrder(
            $tenant,
            $connection,
            'external-1',
            '10001',
            ['state' => 'completed'],
        );
        $order->addItem(null, 'line-1', 'OLD-SKU', 'Discontinued product', '2.0000');

        $order->markHistorical();

        self::assertSame('historical', $order->getStatus());
        self::assertSame('completed', $order->getSourceStatus());
        self::assertSame(['OLD-SKU'], $order->getUnresolvedSkus());

        $order->updateHistoricalState('cancelled');
        self::assertSame('cancelled', $order->getSourceStatus());
        self::assertSame('historical', $order->getStatus());
    }
}
