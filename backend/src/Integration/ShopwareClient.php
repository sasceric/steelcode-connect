<?php

namespace App\Integration;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ShopwareClient
{
    /** @var array<string, array{token: string, expiresAt: int}> */
    private array $accessTokens = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    )
    {
    }

    /** @param array<string, string> $secrets */
    public function test(string $baseUrl, array $secrets): string
    {
        $token = $this->accessToken($baseUrl, $secrets);
        $version = $this->request($baseUrl, $token, 'GET', '/api/_info/version');

        return isset($version['version']) && is_string($version['version'])
            ? sprintf('Connected to Shopware %s.', $version['version'])
            : 'Connected to Shopware.';
    }

    /**
     * @param array<string, string> $secrets
     *
     * @return list<array{id: string, name: string, type: ?string, active: bool, sourceData: array<string, mixed>}>
     */
    public function salesChannels(string $baseUrl, array $secrets): array
    {
        $token = $this->accessToken($baseUrl, $secrets);
        $response = $this->request(
            $baseUrl,
            $token,
            'POST',
            '/api/search/sales-channel',
            [
                'limit' => 500,
                'includes' => [
                    'sales_channel' => [
                        'id',
                        'name',
                        'typeId',
                        'active',
                        'languageId',
                        'currencyId',
                    ],
                ],
            ],
        );

        $channels = [];
        foreach ($response['data'] ?? [] as $channel) {
            if (!is_array($channel)) {
                continue;
            }

            $id = $channel['id'] ?? null;
            $attributes = $channel['attributes'] ?? [];
            $name = is_array($attributes) ? ($attributes['name'] ?? null) : null;
            if (!is_string($id) || !is_string($name) || $name === '') {
                continue;
            }

            $channels[] = [
                'id' => $id,
                'name' => $name,
                'type' => is_string($attributes['typeId'] ?? null) ? $attributes['typeId'] : null,
                'active' => (bool) ($attributes['active'] ?? false),
                'sourceData' => is_array($attributes) ? $attributes : [],
            ];
        }

        return $channels;
    }

    /**
     * Calls the callback once per page of products, including variants.
     *
     * @param array<string, string> $secrets
     * @param callable(int, list<array<string, mixed>>): void $onPage
     */
    public function forEachProductPage(
        string $baseUrl,
        array $secrets,
        callable $onPage,
        string $scope = 'all',
        int $limit = 25,
        ?string $languageId = null,
    ): void {
        if (!in_array($scope, ['all', 'parents', 'variants'], true)) {
            throw new \InvalidArgumentException('The product import scope is invalid.');
        }

        $token = $this->accessToken($baseUrl, $secrets);
        $page = 1;
        $seenPages = [];

        while (true) {
            $criteria = [
                'page' => $page,
                'limit' => $limit,
                'total-count-mode' => 1,
                'associations' => [
                    'tax' => [],
                    'manufacturer' => [],
                    'deliveryTime' => [],
                    'categories' => [],
                    'properties' => [],
                    'options' => [],
                    'tags' => [],
                    'unit' => [],
                    'visibilities' => [],
                    'prices' => [],
                ],
            ];
            if ($scope === 'parents') {
                $criteria['filter'] = [
                    [
                        'type' => 'equals',
                        'field' => 'parentId',
                        'value' => null,
                    ],
                ];
            } elseif ($scope === 'variants') {
                $criteria['filter'] = [
                    [
                        'type' => 'not',
                        'operator' => 'AND',
                        'queries' => [
                            [
                                'type' => 'equals',
                                'field' => 'parentId',
                                'value' => null,
                            ],
                        ],
                    ],
                ];
            }

            $response = $this->request(
                $baseUrl,
                $token,
                'POST',
                '/api/search/product',
                $criteria,
                $languageId === null ? [] : ['sw-language-id' => $languageId],
            );

            $data = array_values(array_filter(
                $response['data'] ?? [],
                static fn (mixed $product): bool => is_array($product),
            ));
            if ($data === []) {
                return;
            }

            $this->ensureNewPage($data, $seenPages, 'products');

            $total = $this->pageTotal($response, $page, $limit, count($data));

            $onPage($total, $data);
            if (count($data) < $limit) {
                return;
            }

            ++$page;
        }
    }

    /** @param array<string, string> $secrets */
    public function productTotal(string $baseUrl, array $secrets): int
    {
        $token = $this->accessToken($baseUrl, $secrets);
        $response = $this->request(
            $baseUrl,
            $token,
            'POST',
            '/api/search/product',
            [
                'limit' => 1,
                'total-count-mode' => 1,
            ],
        );
        $reportedTotal = $response['total'] ?? $response['meta']['total'] ?? null;
        if (!is_numeric($reportedTotal)) {
            throw new \RuntimeException('Shopware did not return the total number of products.');
        }

        return max(0, (int) $reportedTotal);
    }

    /** @param array<string, string> $secrets */
    public function updateProductStock(
        string $baseUrl,
        array $secrets,
        string $externalProductId,
        string $availableQuantity,
    ): void {
        $quantity = (float) $availableQuantity;
        if ($quantity < 0) {
            throw new \InvalidArgumentException('Channel stock cannot be negative.');
        }

        $token = $this->accessToken($baseUrl, $secrets);
        $this->request(
            $baseUrl,
            $token,
            'PATCH',
            '/api/product/'.rawurlencode($externalProductId),
            ['stock' => (int) floor($quantity)],
        );
    }

    /**
     * @param array<string, string> $secrets
     * @param list<array{id: string, stock: int}> $products
     */
    public function updateProductStocks(string $baseUrl, array $secrets, array $products): void
    {
        if ($products === []) {
            return;
        }

        $payload = [];
        foreach ($products as $product) {
            if ($product['id'] === '' || $product['stock'] < 0) {
                throw new \InvalidArgumentException('Each channel stock update needs a product ID and nonnegative quantity.');
            }
            $payload[] = $product;
        }

        $response = $this->request(
            $baseUrl,
            $this->accessToken($baseUrl, $secrets),
            'POST',
            '/api/_action/sync',
            [
                'stock-reconciliation' => [
                    'entity' => 'product',
                    'action' => 'upsert',
                    'payload' => $payload,
                ],
            ],
        );
        if (is_array($response['errors'] ?? null) && $response['errors'] !== []) {
            throw new \RuntimeException('Shopware rejected a bulk stock update.');
        }
    }

    /**
     * @param array<string, string> $secrets
     *
     * @return array<string, list<array{id: string, mediaId: string, position: int}>>
     */
    public function productMediaByProductId(string $baseUrl, array $secrets): array
    {
        $productMediaByProductId = [];

        $this->forEachEntityPage(
            $baseUrl,
            $secrets,
            'product-media',
            function (int $total, array $items) use (&$productMediaByProductId): void {
                foreach ($items as $sourceProductMedia) {
                    $attributes = is_array($sourceProductMedia['attributes'] ?? null)
                        ? $sourceProductMedia['attributes']
                        : $sourceProductMedia;
                    $id = $sourceProductMedia['id'] ?? $attributes['id'] ?? null;
                    $productId = $attributes['productId'] ?? null;
                    $mediaId = $attributes['mediaId'] ?? null;

                    if (
                        !is_string($id)
                        || !is_string($productId)
                        || !is_string($mediaId)
                    ) {
                        continue;
                    }

                    $productMediaByProductId[$productId][] = [
                        'id' => $id,
                        'mediaId' => $mediaId,
                        'position' => max(0, (int) ($attributes['position'] ?? 0)),
                    ];
                }
            },
        );

        return $productMediaByProductId;
    }

    /**
     * @param array<string, string> $secrets
     *
     * @return array<string, list<array{productId: string, salesChannelId: string, visibility: int}>>
     */
    public function productVisibilitiesByProductId(string $baseUrl, array $secrets): array
    {
        /** @var array<string, array<string, array{productId: string, salesChannelId: string, visibility: int}>> $visibilitiesByProductId */
        $visibilitiesByProductId = [];

        $this->forEachEntityPage(
            $baseUrl,
            $secrets,
            'product-visibility',
            function (int $total, array $items) use (&$visibilitiesByProductId): void {
                foreach ($items as $sourceVisibility) {
                    $attributes = is_array($sourceVisibility['attributes'] ?? null)
                        ? $sourceVisibility['attributes']
                        : $sourceVisibility;
                    $productId = $attributes['productId'] ?? null;
                    $salesChannelId = $attributes['salesChannelId'] ?? null;
                    $visibility = $attributes['visibility'] ?? null;
                    if (
                        !is_string($productId)
                        || !is_string($salesChannelId)
                        || !is_numeric($visibility)
                        || !in_array((int) $visibility, [10, 20, 30], true)
                    ) {
                        continue;
                    }

                    $visibilitiesByProductId[$productId][$salesChannelId] = [
                        'productId' => $productId,
                        'salesChannelId' => $salesChannelId,
                        'visibility' => (int) $visibility,
                    ];
                }
            },
        );

        return array_map(
            static fn (array $visibilities): array => array_values($visibilities),
            $visibilitiesByProductId,
        );
    }

    /**
     * Calls the callback once for every Admin API entity search page.
     *
     * @param array<string, string> $secrets
     * @param array<string, mixed> $associations
     * @param array<string, mixed> $criteria
     * @param callable(int, list<array<string, mixed>>, list<array<string, mixed>>): void $onPage
     */
    public function forEachEntityPage(
        string $baseUrl,
        array $secrets,
        string $entity,
        callable $onPage,
        array $associations = [],
        int $limit = 100,
        array $criteria = [],
        ?string $languageId = null,
    ): void {
        $token = $this->accessToken($baseUrl, $secrets);
        $page = 1;
        $seenPages = [];

        while (true) {
            $response = $this->request(
                $baseUrl,
                $token,
                'POST',
                '/api/search/'.$entity,
                array_merge([
                    'page' => $page,
                    'limit' => $limit,
                    'total-count-mode' => 1,
                    'associations' => $associations,
                ], $criteria),
                $languageId === null ? [] : ['sw-language-id' => $languageId],
            );
            if (is_array($response['errors'] ?? null) && $response['errors'] !== []) {
                $firstError = $response['errors'][0] ?? [];
                $detail = is_array($firstError) ? ($firstError['detail'] ?? $firstError['title'] ?? null) : null;
                throw new \RuntimeException(
                    is_string($detail) && $detail !== ''
                        ? 'Shopware '.$entity.' search failed: '.$detail
                        : 'Shopware '.$entity.' search failed.',
                );
            }
            if (!is_array($response['data'] ?? null)) {
                throw new \RuntimeException('Shopware '.$entity.' search returned no data array.');
            }
            $data = array_values(array_filter(
                $response['data'] ?? [],
                static fn (mixed $item): bool => is_array($item),
            ));
            if ($data === []) {
                return;
            }

            $this->ensureNewPage($data, $seenPages, $entity);

            $total = $this->pageTotal($response, $page, $limit, count($data));

            $included = array_values(array_filter(
                is_array($response['included'] ?? null) ? $response['included'] : [],
                static fn (mixed $item): bool => is_array($item),
            ));
            $onPage($total, $data, $included);
            if (count($data) < $limit) {
                return;
            }

            ++$page;
        }
    }

    /**
     * @param array<string, string> $secrets
     *
     * @return array<string, string>
     */
    public function currencyCodes(string $baseUrl, array $secrets): array
    {
        $token = $this->accessToken($baseUrl, $secrets);
        $response = $this->request(
            $baseUrl,
            $token,
            'POST',
            '/api/search/currency',
            [
                'limit' => 500,
            ],
        );

        $currencies = [];
        foreach ($response['data'] ?? [] as $currency) {
            if (!is_array($currency)) {
                continue;
            }

            $attributes = is_array($currency['attributes'] ?? null)
                ? $currency['attributes']
                : $currency;
            $id = $currency['id'] ?? $attributes['id'] ?? null;
            $code = $attributes['isoCode'] ?? null;
            if (!is_string($id) || !is_string($code) || $code === '') {
                continue;
            }

            $currencies[$id] = strtoupper($code);
        }

        return $currencies;
    }

    /**
     * @param array<string, string> $secrets
     *
     * @return array<string, string> Shopware language ID => IETF locale code
     */
    public function languageLocaleCodes(string $baseUrl, array $secrets): array
    {
        $localeCodesById = [];
        $this->forEachEntityPage(
            $baseUrl,
            $secrets,
            'locale',
            function (int $total, array $items) use (&$localeCodesById): void {
                foreach ($items as $item) {
                    $attributes = is_array($item['attributes'] ?? null)
                        ? $item['attributes']
                        : $item;
                    $id = $item['id'] ?? $attributes['id'] ?? null;
                    $code = $attributes['code'] ?? null;
                    if (!is_string($id) || !is_string($code) || trim($code) === '') {
                        continue;
                    }

                    $localeCodesById[$id] = trim($code);
                }
            },
        );

        $languageLocaleCodes = [];
        $this->forEachEntityPage(
            $baseUrl,
            $secrets,
            'language',
            function (int $total, array $items) use (
                $localeCodesById,
                &$languageLocaleCodes,
            ): void {
                foreach ($items as $item) {
                    $attributes = is_array($item['attributes'] ?? null)
                        ? $item['attributes']
                        : $item;
                    $id = $item['id'] ?? $attributes['id'] ?? null;
                    $localeId = $attributes['localeId'] ?? null;
                    if (!is_string($id) || !is_string($localeId)) {
                        continue;
                    }

                    $code = $localeCodesById[$localeId] ?? null;
                    if ($code !== null) {
                        $languageLocaleCodes[$id] = $code;
                    }
                }
            },
        );

        return $languageLocaleCodes;
    }

    /**
     * One bounded page for the reusable destination/reference selectors.
     *
     * @param array<string, string> $secrets
     */
    public function searchPage(
        string $baseUrl,
        array $secrets,
        string $entity,
        array $criteria,
    ): array
    {
        return $this->request(
            $baseUrl,
            $this->accessToken($baseUrl, $secrets),
            'POST',
            '/api/search/'.$entity,
            $criteria,
        );
    }

    public function writeCatalogueEntity(
        string $baseUrl,
        array $secrets,
        string $entity,
        array $payload,
    ): void
    {
        $this->writeCatalogueEntities($baseUrl, $secrets, $entity, [$payload]);
    }

    /** @param list<array<string, mixed>> $payloads */
    public function writeCatalogueEntities(
        string $baseUrl,
        array $secrets,
        string $entity,
        array $payloads,
    ): void
    {
        if ($payloads === [] || count($payloads) > 25) {
            throw new \InvalidArgumentException('Catalogue writes require between one and twenty-five records.');
        }
        $response = $this->request(
            $baseUrl,
            $this->accessToken($baseUrl, $secrets),
            'POST',
            '/api/_action/sync',
            ['connect-catalogue' => ['entity' => $entity, 'action' => 'upsert', 'payload' => array_values($payloads)]],
        );
        if (($response['success'] ?? true) === false || !empty($response['errors'])) {
            throw new ShopwareRequestException(422, 'Shopware did not confirm the catalogue write.');
        }
    }

    public function uploadCatalogueMedia(
        string $baseUrl,
        array $secrets,
        string $id,
        string $path,
        string $extension,
        string $mimeType,
    ): void
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('The product image could not be opened.');
        }
        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($baseUrl, '/').'/api/_action/media/'.$id.'/upload?'.http_build_query([
                    'extension' => $extension,
                    'fileName' => 'connect-'.$id,
                ]),
                [
                    'headers' => [
                        'Authorization' => 'Bearer '.$this->accessToken($baseUrl, $secrets),
                        'Content-Type' => $mimeType,
                    ],
                    'body' => $stream,
                    'timeout' => 60,
                    'max_duration' => 90,
                ],
            );
            $this->throwIfTransient($response);
            if ($response->getStatusCode() >= 300) {
                throw new \RuntimeException('Shopware rejected the product image upload.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function accessToken(string $baseUrl, array $secrets): string
    {
        $cacheKey = hash('sha256', implode("\0", [
            rtrim($baseUrl, '/'),
            $secrets['accessKeyId'] ?? '',
            $secrets['secretAccessKey'] ?? '',
        ]));
        $cached = $this->accessTokens[$cacheKey] ?? null;
        if ($cached !== null && $cached['expiresAt'] > time()) {
            return $cached['token'];
        }

        $tokenResponse = $this->httpClient->request('POST', rtrim($baseUrl, '/').'/api/oauth/token', [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $secrets['accessKeyId'] ?? '',
                'client_secret' => $secrets['secretAccessKey'] ?? '',
            ],
            'timeout' => 10,
            'max_duration' => 15,
        ]);
        $this->throwIfTransient($tokenResponse);
        $response = $tokenResponse->toArray(false);

        if (!isset($response['access_token']) || !is_string($response['access_token'])) {
            throw new \RuntimeException('Shopware did not return an access token.');
        }

        $expiresIn = is_numeric($response['expires_in'] ?? null)
            ? (int) $response['expires_in']
            : 0;
        if ($expiresIn > 30) {
            if (count($this->accessTokens) >= 256) {
                array_shift($this->accessTokens);
            }
            $this->accessTokens[$cacheKey] = [
                'token' => $response['access_token'],
                'expiresAt' => time() + $expiresIn - 30,
            ];
        }

        return $response['access_token'];
    }

    /** @return array<string, mixed> */
    private function request(
        string $baseUrl,
        string $token,
        string $method,
        string $path,
        ?array $json = null,
        array $headers = [],
    ): array {
        $options = [
            'headers' => array_merge([
                'Authorization' => 'Bearer '.$token,
            ], $headers),
            'timeout' => 20,
            'max_duration' => 30,
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }

        $httpResponse = $this->httpClient->request(
            $method,
            rtrim($baseUrl, '/').$path,
            $options,
        );
        $this->throwIfTransient($httpResponse);
        $status = $httpResponse->getStatusCode();
        $body = $httpResponse->getContent(false);
        $response = $body === '' ? [] : json_decode($body, true);

        if (!is_array($response)) {
            throw new \RuntimeException('Shopware returned an invalid response.');
        }
        if ($status < 200 || $status >= 300) {
            $firstError = $response['errors'][0] ?? null;
            $detail = is_array($firstError)
                ? ($firstError['detail'] ?? $firstError['title'] ?? null)
                : null;

            throw new ShopwareRequestException($status, is_string($detail) && $detail !== ''
                ? 'Shopware request failed: '.$detail
                : sprintf('Shopware request failed with HTTP %d.', $status));
        }

        return $response;
    }

    private function throwIfTransient(ResponseInterface $response): void
    {
        try {
            $status = $response->getStatusCode();
            if ($status !== 429 && $status < 500) {
                return;
            }
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? '30';
            $delay = ctype_digit($retryAfter)
                ? (int) $retryAfter
                : max(1, (strtotime($retryAfter) ?: time() + 30) - time());
            throw new IntegrationRetryLaterException(
                'Shopware is temporarily unavailable or rate-limited.',
                min(3600, max(1, $delay)),
            );
        } catch (TransportExceptionInterface) {
            throw new IntegrationRetryLaterException('Shopware could not be reached.');
        }
    }

    /** @param array<string, mixed> $response */
    private function pageTotal(
        array $response,
        int $page,
        int $limit,
        int $itemCount,
    ): int {
        $minimumTotal = (($page - 1) * $limit) + $itemCount;
        $reportedTotal = $response['total'] ?? $response['meta']['total'] ?? null;

        if (is_numeric($reportedTotal) && (int) $reportedTotal >= $minimumTotal) {
            return (int) $reportedTotal;
        }

        return $minimumTotal;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, true> $seenPages
     */
    private function ensureNewPage(
        array $items,
        array &$seenPages,
        string $entity,
    ): void {
        $ids = [];
        foreach ($items as $item) {
            $attributes = is_array($item['attributes'] ?? null)
                ? $item['attributes']
                : $item;
            $id = $item['id'] ?? $attributes['id'] ?? null;
            if (is_scalar($id)) {
                $ids[] = (string) $id;
            }
        }
        if ($ids === []) {
            return;
        }

        $fingerprint = sha1(implode('|', $ids));
        if (isset($seenPages[$fingerprint])) {
            throw new \RuntimeException(sprintf(
                'Shopware returned the same %s page more than once.',
                $entity,
            ));
        }

        $seenPages[$fingerprint] = true;
    }
}
