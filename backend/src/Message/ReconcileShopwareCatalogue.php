<?php

namespace App\Message;

final readonly class ReconcileShopwareCatalogue
{
    public function __construct(
        public string $tenantId,
        public string $connectionId,
    )
    {
    }
}
