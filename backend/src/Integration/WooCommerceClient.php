<?php

namespace App\Integration;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WooCommerceClient
{
    /** Queue calls yield on transient errors; never sleep or blindly replay POST. */
    public function queued(
        string $method,
        string $baseUrl,
        array $secrets,
        string $resource,
        array $body = [],
        array $query = [],
    ): array
    {
        return $this->request($method, $baseUrl, $secrets, $resource, $query, $body, true);
    }

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /** @return array{items: array, total: int, pages: int} */
    public function page(
        string $baseUrl,
        array $secrets,
        string $resource,
        array $query = [],
    ): array
    {
        $result = $this->request(
            'GET',
            $baseUrl,
            $secrets,
            $resource,
            $query,
        );
        if (!array_is_list($result['data'])) {
            throw new \RuntimeException('WooCommerce returned an invalid collection.');
        }
        return [
            'items' => $result['data'],
            'total' => (int) ($result['headers']['x-wp-total'][0] ?? count($result['data'])),
            'pages' => (int) ($result['headers']['x-wp-totalpages'][0] ?? 0),
        ];
    }

    public function object(
        string $method,
        string $baseUrl,
        array $secrets,
        string $resource,
        array $body = [],
    ): array
    {
        $result = $this->request(
            $method,
            $baseUrl,
            $secrets,
            $resource,
            [],
            $body,
        );
        if (array_is_list($result['data'])) {
            throw new \RuntimeException('WooCommerce returned an invalid object.');
        }
        return $result['data'];
    }

    /** @return array{data: array, headers: array} */
    private function request(
        string $method,
        string $baseUrl,
        array $secrets,
        string $resource,
        array $query = [],
        array $body = [],
        bool $queued = false,
    ): array
    {
        if (!in_array($method, ['GET', 'PUT', 'POST'], true)) {
            throw new \InvalidArgumentException('Unsupported WooCommerce request method.');
        }
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !preg_match('#^[a-z0-9/-]+$#', $resource)) {
            throw new \InvalidArgumentException('Invalid WooCommerce API URL or resource.');
        }
        $key = $secrets['consumerKey'] ?? '';
        $secret = $secrets['consumerSecret'] ?? '';
        if ($key === '' || $secret === '') {
            throw new \InvalidArgumentException('WooCommerce API credentials are missing.');
        }
        $url = rtrim($baseUrl, '/') . '/wp-json/wc/v3/' . $resource;
        $options = [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 30,
            'max_duration' => 60,
            'max_redirects' => 0,
        ];
        if ($method !== 'GET') {
            $options['json'] = $body;
        }
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $signedQuery = $query;
            if ($parts['scheme'] === 'https') {
                $options['auth_basic'] = [$key, $secret];
            } else {
                // One-legged OAuth: never send the consumer secret in an HTTP URL.
                $signedQuery = array_merge(
                    $query,
                    [
                        'oauth_consumer_key' => $key,
                        'oauth_nonce' => bin2hex(random_bytes(16)),
                        'oauth_timestamp' => (string) time(),
                        'oauth_signature_method' => 'HMAC-SHA256',
                    ],
                );
                ksort($signedQuery, SORT_STRING);
                $parameters = http_build_query(
                    $signedQuery,
                    '',
                    '&',
                    PHP_QUERY_RFC3986,
                );
                $signatureBase = $method . '&' . rawurlencode($url) . '&' . rawurlencode($parameters);
                $signedQuery['oauth_signature'] = base64_encode(
                    hash_hmac(
                        'sha256',
                        $signatureBase,
                        $secret . '&',
                        true,
                    ),
                );
            }
            $options['query'] = $signedQuery;
            try {
                $response = $this->httpClient->request($method, $url, $options);
                $status = $response->getStatusCode();
                $headers = $response->getHeaders(false);
                if ($queued && in_array($status, [429, 500, 502, 503, 504], true)) {
                    $response->cancel();
                    $retry = $headers['retry-after'][0] ?? '30';
                    $delay = ctype_digit($retry) ? (int) $retry : max(1, (strtotime($retry) ?: time() + 30) - time());
                    throw new IntegrationRetryLaterException('WooCommerce temporarily unavailable.', min(900, max(1, $delay)));
                }
                if (in_array(
                    $status,
                    [
                        429,
                        502,
                        503,
                        504,
                    ],
                    true,
                ) && $method !== 'POST' && $attempt < 2) {
                    $response->cancel();
                    usleep(500000 * ($attempt + 1));
                    continue;
                }
                if ($status < 200 || $status >= 300) {
                    throw new \RuntimeException(sprintf('WooCommerce %s request failed (HTTP %d).', $resource, $status));
                }
                return ['data' => $response->toArray(false), 'headers' => $headers];
            } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $exception) {
                if ($queued) {
                    throw new IntegrationRetryLaterException('WooCommerce connection or response error; publication will resume safely.', 30);
                }
                if (
                    $exception instanceof \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
                    && $method !== 'POST'
                    && $attempt < 2
                ) {
                    // Reads and absolute-stock PUTs are idempotent. POSTs must
                    // never be replayed blindly after an ambiguous timeout.
                    usleep(500000 * ($attempt + 1));
                    continue;
                }
                // HTTP-client exceptions may contain a signed URL. Do not log it.
                throw new \RuntimeException(sprintf('WooCommerce %s request failed: connection or response error.', $resource));
            }
        }
        throw new \RuntimeException('WooCommerce request could not be completed.');
    }

    public function forEachPage(
        string $baseUrl,
        array $secrets,
        string $resource,
        callable $onPage,
        array $query = [],
    ): void
    {
        $page = 1;
        do {
            $result = $this->page(
                $baseUrl,
                $secrets,
                $resource,
                array_merge(
                    $query,
                    [
                        'per_page' => 25,
                        'page' => $page,
                        'orderby' => 'id',
                        'order' => 'asc',
                    ],
                ),
            );
            $onPage($result['total'], $result['items']);
            ++$page;
        } while ($result['items'] !== [] && ($result['pages'] > 0 ? $page <= $result['pages'] : count($result['items']) === 25));
    }
}
