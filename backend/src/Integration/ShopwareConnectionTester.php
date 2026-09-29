<?php

namespace App\Integration;

final class ShopwareConnectionTester
{
    public function __construct(
        private readonly ShopwareClient $client,
    )
    {
    }

    /** @param array<string, string> $secrets */
    public function test(string $baseUrl, array $secrets): string
    {
        return $this->client->test($baseUrl, $secrets);
    }
}
