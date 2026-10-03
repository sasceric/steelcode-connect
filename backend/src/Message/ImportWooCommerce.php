<?php

namespace App\Message;

final readonly class ImportWooCommerce
{
    public function __construct(public string $runId)
    {
    }
}
