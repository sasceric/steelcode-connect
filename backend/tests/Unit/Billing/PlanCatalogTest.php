<?php

namespace App\Tests\Unit\Billing;

use App\Billing\PlanCatalog;
use PHPUnit\Framework\TestCase;

final class PlanCatalogTest extends TestCase
{
    public function testItExposesTheThreeSupportedPlans(): void
    {
        self::assertSame([
            ['code' => 'starter', 'name' => 'Starter', 'price' => 10000, 'productLimit' => 1000],
            ['code' => 'growth', 'name' => 'Growth', 'price' => 20000, 'productLimit' => 5000],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'price' => 30000, 'productLimit' => null],
        ], PlanCatalog::all());
    }

    public function testItFindsPlansByTheirCode(): void
    {
        self::assertSame('Growth', PlanCatalog::find('growth')['name']);
        self::assertNull(PlanCatalog::find('unknown'));
    }
}
