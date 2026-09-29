<?php

namespace App\Message;

final readonly class ImportShopwareProducts
{
    public function __construct(
        public string $runId,
    ) {
    }
}
