<?php

namespace App\Message;

final readonly class SyncWooCommerceSalesConnection
{
    public function __construct(
        public string $tenantId,
        public string $connectionId,
    )
    {
    }
}
