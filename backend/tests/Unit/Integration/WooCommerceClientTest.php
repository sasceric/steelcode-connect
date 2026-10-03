<?php

namespace App\Tests\Unit\Integration;

use App\Integration\WooCommerceClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Exception\TransportException;

final class WooCommerceClientTest extends TestCase
{
    public function testHttpUsesSignedOAuthAndPagesInBatchesOf25(): void
    {
        $requests = 0;
        $http = new MockHttpClient(
            function (
                string $method,
                string $url,
            ) use (&$requests): MockResponse {
                ++$requests;
                self::assertSame('GET', $method);
                parse_str(parse_url($url, PHP_URL_QUERY), $query);
                self::assertSame('25', $query['per_page']);
                self::assertSame((string) $requests, $query['page']);
                self::assertSame('id', $query['orderby']);
                self::assertSame('asc', $query['order']);
                self::assertStringNotContainsString('private-secret', $url);
                $signature = $query['oauth_signature'];
                unset($query['oauth_signature']);
                ksort($query, SORT_STRING);
                $base = 'GET&' . rawurlencode('http://woo.test/wp-json/wc/v3/products') . '&' . rawurlencode(
                    http_build_query(
                        $query,
                        '',
                        '&',
                        PHP_QUERY_RFC3986,
                    ),
                );
                self::assertSame(
                    base64_encode(
                        hash_hmac(
                            'sha256',
                            $base,
                            'private-secret&',
                            true,
                        ),
                    ),
                    $signature,
                );
                return new MockResponse(
                    json_encode([['id' => $requests]], JSON_THROW_ON_ERROR),
                    ['response_headers' => ['X-WP-Total: 26', 'X-WP-TotalPages: 2']],
                );
            },
        );
        $pages = [];
        (new WooCommerceClient($http))->forEachPage(
            'http://woo.test',
            ['consumerKey' => 'key', 'consumerSecret' => 'private-secret'],
            'products',
            function (
                int $total,
                array $items,
            ) use (&$pages): void {
                self::assertSame(26, $total);
                $pages[] = $items;
            },
        );
        self::assertCount(2, $pages);
        self::assertSame(2, $requests);
    }

    public function testHttpsUsesBasicAuthenticationWithoutCredentialsInQuery(): void
    {
        $http = new MockHttpClient(
            function (
                string $method,
                string $url,
                array $options,
            ): MockResponse {
                self::assertStringNotContainsString('secret', $url);
                self::assertStringNotContainsString('oauth_', $url);
                self::assertContains('Authorization: Basic ' . base64_encode('key:secret'), $options['headers']);
                self::assertSame(0, $options['max_redirects']);
                return new MockResponse('[]');
            },
        );
        (new WooCommerceClient($http))->page('https://woo.test', ['consumerKey' => 'key', 'consumerSecret' => 'secret'], 'orders');
    }

    public function testErrorsDoNotExposeProviderResponseOrCredentials(): void
    {
        $client = new WooCommerceClient(new MockHttpClient(new MockResponse('{"secret":"private-secret"}', ['http_code' => 401])));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WooCommerce orders request failed (HTTP 401).');
        $client->page(
            'http://woo.test',
            ['consumerKey' => 'key', 'consumerSecret' => 'private-secret'],
            'orders',
        );
    }

    public function testPutSignsMethodAndSendsAbsoluteStockAsJson(): void
    {
        $http = new MockHttpClient(
            function (
                string $method,
                string $url,
                array $options,
            ): MockResponse {
                self::assertSame('PUT', $method);
                self::assertSame(['stock_quantity' => 8], json_decode($options['body'], true));
                parse_str(parse_url($url, PHP_URL_QUERY), $query);
                $signature = $query['oauth_signature'];
                unset($query['oauth_signature']);
                ksort($query, SORT_STRING);
                $base = 'PUT&' . rawurlencode('http://woo.test/wp-json/wc/v3/products/10/variations/11') . '&' . rawurlencode(
                    http_build_query(
                        $query,
                        '',
                        '&',
                        PHP_QUERY_RFC3986,
                    ),
                );
                self::assertSame(
                    base64_encode(
                        hash_hmac(
                            'sha256',
                            $base,
                            'secret&',
                            true,
                        ),
                    ),
                    $signature,
                );
                return new MockResponse('{"id":11,"stock_quantity":8}');
            },
        );
        $result = (new WooCommerceClient($http))->object(
            'PUT',
            'http://woo.test',
            ['consumerKey' => 'key', 'consumerSecret' => 'secret'],
            'products/10/variations/11',
            ['stock_quantity' => 8],
        );
        self::assertSame(8, $result['stock_quantity']);
    }

    public function testTransientReadTimeoutIsRetriedWithFreshSignature(): void
    {
        $requests = 0;
        $signatures = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$requests, &$signatures): MockResponse {
            ++$requests;
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $signatures[] = $query['oauth_signature'];
            if ($requests === 1) {
                throw new TransportException('Sensitive signed provider URL must never appear in errors.');
            }

            return new MockResponse('[]');
        });
        $result = (new WooCommerceClient($http))->page('http://woo.test', ['consumerKey' => 'key', 'consumerSecret' => 'secret'], 'products');
        self::assertSame([], $result['items']);
        self::assertSame(2, $requests);
        self::assertNotSame($signatures[0], $signatures[1]);
    }

    public function testReadTimeoutRetriesAreBoundedAndErrorsStaySanitized(): void
    {
        $requests = 0;
        $http = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;
            throw new TransportException('private-secret in a signed URL');
        });
        try {
            (new WooCommerceClient($http))->page('http://woo.test', ['consumerKey' => 'key', 'consumerSecret' => 'secret'], 'orders');
            self::fail('The request must fail after bounded retries.');
        } catch (\RuntimeException $exception) {
            self::assertSame('WooCommerce orders request failed: connection or response error.', $exception->getMessage());
        }
        self::assertSame(3, $requests);
    }

    public function testPostTimeoutIsNotReplayed(): void
    {
        $requests = 0;
        $http = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;
            throw new TransportException('Ambiguous POST timeout');
        });
        try {
            (new WooCommerceClient($http))->object('POST', 'http://woo.test', ['consumerKey' => 'key', 'consumerSecret' => 'secret'], 'orders');
            self::fail('The POST must report its ambiguous failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('WooCommerce orders request failed: connection or response error.', $exception->getMessage());
        }
        self::assertSame(1, $requests);
    }
}
