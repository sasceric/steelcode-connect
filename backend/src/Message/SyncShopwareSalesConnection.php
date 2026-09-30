<?php

namespace App\Message;

final class SyncShopwareSalesConnection
{
    public function __construct(public readonly string $connectionId)
    {
    }
}
