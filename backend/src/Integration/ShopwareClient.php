<?php

namespace App\Integration;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ShopwareClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
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
     * @param callable(int, list<array<string, mixed>>): void $onPage
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
            $data = array_values(array_filter(
                $response['data'] ?? [],
                static fn (mixed $item): bool => is_array($item),
            ));
            if ($data === []) {
                return;
            }

            $this->ensureNewPage($data, $seenPages, $entity);

            $total = $this->pageTotal($response, $page, $limit, count($data));

            $onPage($total, $data);
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

    /** @param array<string, string> $secrets */
    private function accessToken(string $baseUrl, array $secrets): string
    {
        $response = $this->httpClient->request('POST', rtrim($baseUrl, '/').'/api/oauth/token', [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $secrets['accessKeyId'] ?? '',
                'client_secret' => $secrets['secretAccessKey'] ?? '',
            ],
            'timeout' => 10,
        ])->toArray(false);

        if (!isset($response['access_token']) || !is_string($response['access_token'])) {
            throw new \RuntimeException('Shopware did not return an access token.');
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
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request(
            $method,
            rtrim($baseUrl, '/').$path,
            $options,
        )->toArray(false);

        if (!is_array($response)) {
            throw new \RuntimeException('Shopware returned an invalid response.');
        }

        return $response;
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
