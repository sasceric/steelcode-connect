<?php

namespace App\Tests\Unit\Integration;

use App\Integration\ShopwareClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ShopwareSalesPagingTest extends TestCase
{
    public function testItReusesAnUnexpiredTokenForBulkStockUpdates(): void
    {
        $tokenRequests = 0;
        $stockUpdates = 0;
        $http = new MockHttpClient(
            function (string $method, string $url) use (&$tokenRequests, &$stockUpdates): MockResponse {
                if (str_ends_with($url, '/api/oauth/token')) {
                    ++$tokenRequests;

                    return new MockResponse(json_encode([
                        'access_token' => 'test-token',
                        'expires_in' => 3600,
                    ], JSON_THROW_ON_ERROR));
                }

                self::assertSame('PATCH', $method);
                ++$stockUpdates;

                return new MockResponse('', ['http_code' => 204]);
            },
            'https://shop.example.test',
        );
        $client = new ShopwareClient($http);
        $secrets = ['accessKeyId' => 'key', 'secretAccessKey' => 'secret'];

        $client->updateProductStock('https://shop.example.test', $secrets, 'first', '12.0000');
        $client->updateProductStock('https://shop.example.test', $secrets, 'second', '13.0000');

        self::assertSame(1, $tokenRequests);
        self::assertSame(2, $stockUpdates);
    }

    public function testItSendsAStockReconciliationBatchWithoutReplacingOtherProductFields(): void
    {
        $http = new MockHttpClient(
            function (string $method, string $url, array $options): MockResponse {
                if (str_ends_with($url, '/api/oauth/token')) {
                    return new MockResponse(json_encode([
                        'access_token' => 'test-token',
                        'expires_in' => 3600,
                    ], JSON_THROW_ON_ERROR));
                }

                self::assertSame('POST', $method);
                self::assertStringEndsWith('/api/_action/sync', $url);
                $body = json_decode($options['body'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
                self::assertSame([
                    'stock-reconciliation' => [
                        'entity' => 'product',
                        'action' => 'upsert',
                        'payload' => [
                            ['id' => 'first', 'stock' => 12],
                            ['id' => 'second', 'stock' => 0],
                        ],
                    ],
                ], $body);

                return new MockResponse(json_encode([
                    'data' => ['stock-reconciliation' => ['first', 'second']],
                ], JSON_THROW_ON_ERROR));
            },
            'https://shop.example.test',
        );

        (new ShopwareClient($http))->updateProductStocks(
            'https://shop.example.test',
            ['accessKeyId' => 'key', 'secretAccessKey' => 'secret'],
            [
                ['id' => 'first', 'stock' => 12],
                ['id' => 'second', 'stock' => 0],
            ],
        );
    }

    public function testItFailsTheRunOnAShopwareSearchError(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'test-token'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'errors' => [['detail' => 'Invalid criteria']],
            ], JSON_THROW_ON_ERROR), ['http_code' => 400]),
        ]);
        $client = new ShopwareClient($http);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid criteria');
        $client->forEachEntityPage(
            'https://shop.example.test',
            ['accessKeyId' => 'key', 'secretAccessKey' => 'secret'],
            'order',
            static function (): void {
                self::fail('An API error must not look like an empty import.');
            },
        );
    }

    public function testItLoadsBoundedPagesAndPassesIncludedAssociations(): void
    {
        $requestedPages = [];
        $http = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requestedPages): MockResponse {
                if (str_ends_with($url, '/api/oauth/token')) {
                    return new MockResponse(json_encode(['access_token' => 'test-token'], JSON_THROW_ON_ERROR));
                }

                self::assertSame('POST', $method);
                self::assertStringEndsWith('/api/search/order', $url);
                $body = json_decode($options['body'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
                $requestedPages[] = $body['page'];
                self::assertSame(25, $body['limit']);
                self::assertSame('orderDateTime', $body['filter'][0]['field']);

                $count = $body['page'] === 3 ? 1 : 25;
                $data = [];
                for ($index = 0; $index < $count; ++$index) {
                    $data[] = ['type' => 'order', 'id' => sprintf('order-%d-%d', $body['page'], $index)];
                }

                return new MockResponse(json_encode([
                    'total' => 51,
                    'data' => $data,
                    'included' => [['type' => 'currency', 'id' => 'eur', 'attributes' => ['isoCode' => 'EUR']]],
                ], JSON_THROW_ON_ERROR));
            },
            'https://shop.example.test',
        );
        $client = new ShopwareClient($http);
        $received = 0;
        $client->forEachEntityPage(
            'https://shop.example.test',
            ['accessKeyId' => 'key', 'secretAccessKey' => 'secret'],
            'order',
            function (int $total, array $items, array $included) use (&$received): void {
                self::assertSame(51, $total);
                self::assertSame('EUR', $included[0]['attributes']['isoCode']);
                $received += count($items);
            },
            ['lineItems' => []],
            25,
            ['filter' => [[
                'type' => 'range',
                'field' => 'orderDateTime',
                'parameters' => ['gte' => '2026-01-01'],
            ]]],
        );

        self::assertSame([1, 2, 3], $requestedPages);
        self::assertSame(51, $received);
    }
}
