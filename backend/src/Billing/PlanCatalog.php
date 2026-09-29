<?php

namespace App\Billing;

final class PlanCatalog
{
    /** @var array<string, array{code: string, name: string, price: int, productLimit: int|null}> */
    private const PLANS = [
        'starter' => ['code' => 'starter', 'name' => 'Starter', 'price' => 10000, 'productLimit' => 1000],
        'growth' => ['code' => 'growth', 'name' => 'Growth', 'price' => 20000, 'productLimit' => 5000],
        'enterprise' => ['code' => 'enterprise', 'name' => 'Enterprise', 'price' => 30000, 'productLimit' => null],
    ];

    /** @return list<array{code: string, name: string, price: int, productLimit: int|null}> */
    public static function all(): array
    {
        return array_values(self::PLANS);
    }

    /** @return array{code: string, name: string, price: int, productLimit: int|null}|null */
    public static function find(string $code): ?array
    {
        return self::PLANS[$code] ?? null;
    }
}
