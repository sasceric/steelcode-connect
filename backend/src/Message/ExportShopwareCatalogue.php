<?php

namespace App\Message;

final readonly class ExportShopwareCatalogue
{
    public function __construct(
        public string $tenantId,
        public string $connectionId,
        public string $planId,
        public string $runId,
        public bool $preview,
        public ?string $workToken = null,
    )
    {
    }
}
