<?php

namespace App\Message;

final readonly class ExportWooCommerceCatalogue
{
    public function __construct(
        public string $tenantId,
        public string $connectionId,
        public string $planId,
        public string $runId,
        public bool $preview = true,
        public ?string $workToken = null,
    )
    {
    }
}
