<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Invoice;
use App\Entity\Tenant;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    public function testItStoresTheBillingPeriodAndInterval(): void
    {
        $invoice = new Invoice(
            new Tenant('SteelCode'),
            '2026-001',
            'growth',
            216000,
            new \DateTimeImmutable('2026-07-28 14:30:00'),
            'annual',
        );

        self::assertSame('2026-07-28', $invoice->getBillingPeriod()->format('Y-m-d'));
        self::assertSame('annual', $invoice->getBillingInterval());
        self::assertSame('2026-001', $invoice->getNumber());
    }
}
