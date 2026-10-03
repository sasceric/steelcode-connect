<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSecret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CatalogueExportReferences
{
    public const TABLES = [
        'category' => 'categories',
        'manufacturer' => 'manufacturers',
        'brand' => 'brands',
        'property' => 'properties',
        'propertyGroup' => 'property_groups',
        'tax' => 'taxes',
        'unit' => 'units',
        'deliveryTime' => 'delivery_times',
        'customField' => 'custom_fields',
        'currency' => 'currencies',
        'locale' => 'locales',
        'product' => 'products',
    ];

    public const ENTITIES = [
        'category' => 'category',
        'manufacturer' => 'product-manufacturer',
        'property' => 'property-group-option',
        'tax' => 'tax',
        'currency' => 'currency',
        'locale' => 'language',
        'unit' => 'unit',
        'deliveryTime' => 'delivery-time',
        'customField' => 'custom-field',
        'salesChannel' => 'sales-channel',
        'product' => 'product',
    ];

    public function __construct(
        private readonly ShopwareClient $client,
        private readonly SecretCipher $cipher,
        private readonly ?CacheInterface $cache = null,
        private readonly ?WooCommerceCatalogueReferences $woo = null,
    )
    {
    }

    public function credentials(IntegrationConnection $connection, EntityManagerInterface $manager): array
    {
        $secrets = [];
        foreach ($manager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
            $secrets[$secret->getSecretKey()] = $this->cipher->decrypt($secret->getCiphertext(), $secret->getNonce());
        }

        return $secrets;
    }

    public function standardMappings(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $settings,
    ): array
    {
        if ($connection->getConnectorKey() === 'woocommerce') {
            return $this->woo->settings($connection, $manager, $settings);
        }
        $url = $connection->getConfiguration()['baseUrl'];
        $secrets = $this->credentials($connection, $manager);
        $lookup = function () use ($url, $secrets): array {
            return [
                'currency' => $this->client->currencyCodes($url, $secrets),
                'locale' => $this->client->languageLocaleCodes($url, $secrets),
            ];
        };
        $cacheKey = 'catalogue.codes.'.hash('sha256', json_encode([
            (string) $connection->getTenant()->getId(), (string) $connection->getId(), $url, $secrets,
        ], JSON_THROW_ON_ERROR));
        $codesByType = $this->cache?->get($cacheKey, function (ItemInterface $item) use ($lookup): array {
            $item->expiresAfter(300);

            return $lookup();
        }) ?? $lookup();
        foreach ($codesByType as $type => $codes) {
            $idsByCode = [];
            foreach ($codes as $id => $code) {
                $idsByCode[$code][] = $id;
            }
            $table = self::TABLES[$type];
            foreach ($manager->getConnection()->fetchAllAssociative("SELECT id, code FROM $table") as $local) {
                if (!isset($settings['mappings'][$type][$local['id']]) && count($idsByCode[$local['code']] ?? []) === 1 && $this->owns($connection, $manager, $type, $local['id'])) {
                    $settings['mappings'][$type][$local['id']] = $idsByCode[$local['code']][0];
                }
            }
        }

        return $settings;
    }

    public function localPage(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        string $type,
        int $page,
        string $search,
        array $ids = [],
    ): array
    {
        $table = self::TABLES[$type] ?? throw new \InvalidArgumentException('Invalid local reference type.');
        $tenant = $connection->getTenant()->getId()->toRfc4122();
        $label = match ($type) {
            'category', 'manufacturer', 'brand' => "COALESCE((SELECT t.name FROM {$type}_translations t WHERE t.{$type}_id = r.id ORDER BY t.locale_id LIMIT 1), r.id::text)",
            'product' => "COALESCE((SELECT t.name FROM product_translations t WHERE t.product_id = r.id ORDER BY t.locale_id LIMIT 1), (SELECT t.name FROM product_translations t JOIN products p ON p.id = t.product_id AND p.tenant_id = :tenant WHERE t.product_id = r.parent_id ORDER BY t.locale_id LIMIT 1), r.sku, r.id::text)",
            'currency', 'locale', 'unit' => 'r.code',
            'customField' => 'r.technical_name',
            'deliveryTime' => "COALESCE(r.labels->>'en-GB', r.id::text)",
            'property' => "(SELECT g.name FROM property_groups g WHERE g.id = r.property_group_id AND g.tenant_id = :tenant) || ' / ' || r.name",
            default => 'r.name',
        };
        $scope = match ($type) {
            'currency' => 'EXISTS (SELECT 1 FROM tenant_currencies tc WHERE tc.currency_id = r.id AND tc.tenant_id = :tenant AND tc.enabled = TRUE)',
            'locale' => "EXISTS (SELECT 1 FROM tenants t WHERE t.id = :tenant AND jsonb_exists(t.enabled_snippet_locales::jsonb, r.code))",
            default => 'r.tenant_id = :tenant',
        };
        $searchField = $type === 'product' ? "label || ' ' || COALESCE(sku, '')" : 'label';
        $extra = $type === 'product' ? ', r.sku' : '';
        $idFilter = '';
        $parameters = ['tenant' => $tenant, 'search' => '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%', 'offset' => ($page - 1) * 25];
        if ($ids !== []) {
            $placeholders = [];
            foreach ($ids as $index => $id) {
                $parameters['id'.$index] = $id;
                $placeholders[] = ':id'.$index;
            }
            $idFilter = ' AND r.id IN ('.implode(', ', $placeholders).')';
        }
        $rows = $manager->getConnection()->fetchAllAssociative(
            "SELECT * FROM (SELECT r.id, $label AS label $extra FROM $table r WHERE $scope $idFilter) refs WHERE $searchField ILIKE :search ORDER BY label, id LIMIT 26 OFFSET :offset",
            $parameters,
            ['offset' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
        $hasMore = count($rows) > 25;

        return ['items' => array_slice($rows, 0, 25), 'pagination' => ['hasMore' => $hasMore]];
    }

    public function targetPage(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        string $type,
        int $page,
        string $search,
        array $ids = [],
    ): array
    {
        if ($connection->getConnectorKey() === 'woocommerce') {
            return $this->woo->page($connection, $manager, $type, $page, $search, $ids, $this->credentials($connection, $manager));
        }
        $entity = self::ENTITIES[$type] ?? throw new \InvalidArgumentException('Invalid destination reference type.');
        $criteria = ['page' => $page, 'limit' => 25, 'total-count-mode' => 1];
        if ($ids !== []) {
            $criteria['ids'] = $ids;
        }
        if ($search !== '') {
            $criteria['term'] = $search;
        }
        if ($type === 'property') {
            $criteria['associations'] = ['group' => []];
        }
        if ($type === 'salesChannel') {
            $criteria['filter'] = [['type' => 'equals', 'field' => 'active', 'value' => true]];
        }
        $response = $this->client->searchPage(
            $connection->getConfiguration()['baseUrl'],
            $this->credentials($connection, $manager),
            $entity,
            $criteria,
        );
        $included = [];
        foreach ($response['included'] ?? [] as $record) {
            $included[$record['id']] = $record['attributes'] ?? [];
        }
        $items = [];
        foreach ($response['data'] ?? [] as $record) {
            $attributes = $record['attributes'] ?? $record;
            $label = $attributes['translated']['name'] ?? $attributes['name'] ?? $attributes['isoCode'] ?? $record['id'];
            if ($type === 'property') {
                $group = $included[$attributes['groupId'] ?? ''] ?? [];
                $label = ($group['translated']['name'] ?? $group['name'] ?? '').' / '.$label;
            }
            $items[] = ['id' => $record['id'], 'label' => $label];
        }
        $total = (int) ($response['total'] ?? $response['meta']['total'] ?? count($items));

        return ['items' => $items, 'pagination' => ['hasMore' => $page * 25 < $total]];
    }

    public function validateLocalSettings(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $settings,
    ): void
    {
        $sets = [
            'category' => $settings['categoryIds'],
            'brand' => $settings['brandIds'],
            'manufacturer' => $settings['manufacturerIds'],
            'product' => array_merge($settings['productIds'], $settings['excludeIds']),
        ];
        if (!empty($settings['destinationLocaleId'])) {
            $sets['locale'] = [$settings['destinationLocaleId']];
        }
        foreach ($settings['mappings'] as $type => $mappings) {
            if (in_array($type, ['product', 'locale', 'currency'], true) && count(array_unique($mappings)) !== count($mappings)) {
                throw new \InvalidArgumentException('Product identities, languages and currencies require one-to-one mappings.');
            }
            $sets[$type] = array_merge($sets[$type] ?? [], array_keys($mappings));
        }
        foreach ($sets as $type => $ids) {
            foreach (array_unique($ids) as $id) {
                if (!$this->owns($connection, $manager, $type, $id)) {
                    throw new \InvalidArgumentException('An export selection or mapping does not belong to this tenant.');
                }
            }
        }
    }

    public function owns(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        string $type,
        string $id,
    ): bool
    {
        $table = self::TABLES[$type] ?? throw new \InvalidArgumentException('Invalid local reference type.');
        $scope = match ($type) {
            'currency' => 'EXISTS (SELECT 1 FROM tenant_currencies tc WHERE tc.currency_id = r.id AND tc.tenant_id = :tenant AND tc.enabled = TRUE)',
            'locale' => "EXISTS (SELECT 1 FROM tenants t WHERE t.id = :tenant AND jsonb_exists(t.enabled_snippet_locales::jsonb, r.code))",
            default => 'r.tenant_id = :tenant',
        };

        return (bool) $manager->getConnection()->fetchOne(
            "SELECT 1 FROM $table r WHERE r.id = :id AND $scope",
            ['id' => $id, 'tenant' => $connection->getTenant()->getId()->toRfc4122()],
        );
    }
}
