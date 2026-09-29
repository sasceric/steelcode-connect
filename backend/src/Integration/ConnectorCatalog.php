<?php

namespace App\Integration;

final class ConnectorCatalog
{
    /** @return list<array{key: string, name: string, directions: list<string>, credentials: list<string>, configuration: list<string>}> */
    public static function all(): array
    {
        return [
            ['key' => 'shopware', 'name' => 'Shopware 6', 'directions' => ['source', 'channel'], 'credentials' => ['accessKeyId', 'secretAccessKey'], 'configuration' => ['baseUrl']],
            ['key' => 'woocommerce', 'name' => 'WooCommerce', 'directions' => ['source', 'channel'], 'credentials' => ['consumerKey', 'consumerSecret'], 'configuration' => ['baseUrl']],
            ['key' => 'olx', 'name' => 'OLX', 'directions' => ['source', 'channel'], 'credentials' => ['accessToken'], 'configuration' => ['shopId']],
            ['key' => 'ananas', 'name' => 'Ananas', 'directions' => ['source', 'channel'], 'credentials' => ['apiKey', 'clientId', 'clientSecret'], 'configuration' => []],
        ];
    }

    /** @return array{key: string, name: string, directions: list<string>, credentials: list<string>}|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $connector) {
            if ($connector['key'] === $key) {
                return $connector;
            }
        }

        return null;
    }
}
