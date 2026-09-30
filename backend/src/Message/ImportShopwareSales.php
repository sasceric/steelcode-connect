<?php

namespace App\Message;

final readonly class ImportShopwareSales
{
    public function __construct(
        public string $runId,
    ) {
    }
}
