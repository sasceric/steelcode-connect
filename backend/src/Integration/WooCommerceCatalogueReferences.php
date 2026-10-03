<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSecret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class WooCommerceCatalogueReferences
{
    public const RESOURCES = [
        'category' => 'products/categories',
        'brand' => 'products/brands',
        'propertyGroup' => 'products/attributes',
        'tag' => 'products/tags',
        'product' => 'products',
    ];

    public function __construct(
        private readonly WooCommerceClient $client,
        private readonly SecretCipher $cipher,
        private readonly CacheInterface $cache,
    )
    {
    }

    public function settings(IntegrationConnection $connection, EntityManagerInterface $manager, array $settings, bool $fresh = false): array
    {
        $secrets = [];
        foreach ($manager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
            $secrets[$secret->getSecretKey()] = $this->cipher->decrypt($secret->getCiphertext(), $secret->getNonce());
        }
        $url = $connection->getConfiguration()['baseUrl'];
        $key = 'woo.catalogue.settings.'.hash('sha256', json_encode([
            (string) $connection->getTenant()->getId(), (string) $connection->getId(), $url, $secrets,
        ], JSON_THROW_ON_ERROR));
        if ($fresh) {
            $this->cache->delete($key);
        }
        $settings['_woo'] = $this->cache->get($key, function (ItemInterface $item) use ($url, $secrets): array {
            $item->expiresAfter(60);
            $store = [];
            foreach (['settings/general', 'settings/tax', 'settings/products'] as $resource) {
                $response = $this->client->queued('GET', $url, $secrets, $resource);
                foreach ($response['data'] as $option) {
                    $store[$option['id']] = $option['value'] ?? null;
                }
            }
            $rates = $this->client->queued('GET', $url, $secrets, 'taxes', query: ['per_page' => 100]);
            if ((int) ($rates['headers']['x-wp-total'][0] ?? 0) > 100) {
                throw new \DomainException('This tax configuration requires an explicit class mapping and a larger tax-rule adapter.');
            }
            $store['_taxRates'] = $rates['data'];

            return $store;
        });

        return $settings;
    }

    public function page(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        string $type,
        int $page,
        string $search,
        array $ids,
        array $secrets,
    ): array
    {
        $url = $connection->getConfiguration()['baseUrl'];
        if ($type === 'tax') {
            $records = $this->client->queued('GET', $url, $secrets, 'taxes/classes')['data'];
            $items = [['id' => 'standard', 'label' => 'Standard']];
            foreach ($records as $record) {
                $items[] = ['id' => $record['slug'], 'label' => $record['name']];
            }
            $items = array_values(array_filter($items, static fn (array $item): bool =>
                ($ids === [] || in_array($item['id'], $ids, true))
                && ($search === '' || mb_stripos($item['label'], $search) !== false)
            ));

            return ['items' => $items, 'pagination' => ['hasMore' => false]];
        }
        $resource = self::RESOURCES[$type] ?? throw new \InvalidArgumentException('This reference is not supported by native WooCommerce.');
        // Native Woo attributes do not implement the paginated taxonomy collection contract.
        if ($type === 'propertyGroup') {
            $records = $this->client->queued('GET', $url, $secrets, $resource)['data'];
            $records = array_values(array_filter($records, static fn (array $record): bool =>
                ($ids === [] || in_array((string) $record['id'], $ids, true))
                && ($search === '' || mb_stripos($record['name'], $search) !== false)
            ));
            $items = array_map(static fn (array $record): array => [
                'id' => (string) $record['id'],
                'label' => $record['name'],
            ], array_slice($records, ($page - 1) * 25, 25));

            return ['items' => $items, 'pagination' => ['hasMore' => $page * 25 < count($records)]];
        }
        $query = ['page' => $page, 'per_page' => 25];
        if ($ids !== []) {
            $query['include'] = implode(',', $ids);
        }
        if ($search !== '') {
            $query['search'] = $search;
        }
        $response = $this->client->queued('GET', $url, $secrets, $resource, query: $query);
        $items = array_map(static fn (array $record): array => [
            'id' => (string) $record['id'],
            'label' => $record['name'].(!empty($record['sku']) ? ' · '.$record['sku'] : ''),
        ], $response['data']);

        return ['items' => $items, 'pagination' => ['hasMore' => $page * 25 < (int) ($response['headers']['x-wp-total'][0] ?? count($items))]];
    }
}
