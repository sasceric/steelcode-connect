<?php

namespace App\Tests\Unit\Integration;

use App\Integration\IntegrationRetryLaterException;
use App\Integration\ShopwareClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ShopwareClientRetryTest extends TestCase
{
    public function testRateLimitHonoursRetryAfterWithoutExposingTheResponse(): void
    {
        $client = new ShopwareClient(new MockHttpClient([
            new MockResponse('{"access_token":"token","expires_in":600}'),
            new MockResponse('{"secret":"never-log-this"}', [
                'http_code' => 429,
                'response_headers' => ['Retry-After: 120'],
            ]),
        ]));

        try {
            $client->test('https://shop.test', ['accessKeyId' => 'key', 'secretAccessKey' => 'secret']);
            self::fail('A rate-limited request must yield to the queue.');
        } catch (IntegrationRetryLaterException $exception) {
            self::assertSame(120, $exception->delaySeconds);
            self::assertStringNotContainsString('never-log-this', $exception->getMessage());
        }
    }

    public function testOAuthOutageIsRetryableBeforeAnyApiWrite(): void
    {
        $client = new ShopwareClient(new MockHttpClient(new MockResponse('', ['http_code' => 503])));
        $this->expectException(IntegrationRetryLaterException::class);
        $client->updateProductStock('https://shop.test', ['accessKeyId' => 'key', 'secretAccessKey' => 'secret'], 'product', '1');
    }

    public function testRequestsHaveFiniteDurationAndOAuthIsReused(): void
    {
        $calls = 0;
        $client = new ShopwareClient(new MockHttpClient(function (
            string $method,
            string $url,
            array $options,
        ) use (&$calls): MockResponse {
            ++$calls;
            $oauth = str_ends_with($url, '/api/oauth/token');
            self::assertSame($oauth ? 15.0 : 30.0, (float) $options['max_duration']);
            self::assertSame($oauth ? 10.0 : 20.0, (float) $options['timeout']);

            return new MockResponse($oauth ? '{"access_token":"token","expires_in":600}' : '{"version":"6.7"}');
        }));

        $secrets = ['accessKeyId' => 'key', 'secretAccessKey' => 'secret'];
        $client->test('https://shop.test', $secrets);
        $client->test('https://shop.test', $secrets);
        self::assertSame(3, $calls);
    }

    public function testCatalogueBatchIsBoundedAndUsesOneSyncRequest(): void
    {
        $calls = 0;
        $payloads = [
            ['id' => str_repeat('a', 32), 'productNumber' => 'BATCH-1'],
            ['id' => str_repeat('b', 32), 'productNumber' => 'BATCH-2'],
        ];
        $client = new ShopwareClient(new MockHttpClient(function (string $method, string $url, array $options) use (&$calls, $payloads): MockResponse {
            ++$calls;
            if (str_ends_with($url, '/api/oauth/token')) {
                return new MockResponse('{"access_token":"token","expires_in":600}');
            }
            self::assertStringEndsWith('/api/_action/sync', $url);
            $body = json_decode($options['body'], true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($payloads, $body['connect-catalogue']['payload']);

            return new MockResponse('{}');
        }));
        $secrets = ['accessKeyId' => 'key', 'secretAccessKey' => 'secret'];
        $client->writeCatalogueEntities('https://shop.test', $secrets, 'product', $payloads);
        self::assertSame(2, $calls);
        try {
            $client->writeCatalogueEntities('https://shop.test', $secrets, 'product', array_fill(0, 26, $payloads[0]));
            self::fail('An oversized batch must be rejected before any HTTP request.');
        } catch (\InvalidArgumentException) {
            self::assertSame(2, $calls);
        }
    }
}
