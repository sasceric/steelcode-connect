<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\IntegrationSalesChannel;
use App\Entity\Locale;
use App\Entity\Tenant;
use App\Service\SeoUrlService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ShopwareSeoUrlImporter
{
    private const BATCH_SIZE = 100;

    private const PRODUCT_ROUTE = 'frontend.detail.page';
    private const CATEGORY_ROUTE = 'frontend.navigation.page';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShopwareClient $shopwareClient,
        private readonly SeoUrlService $seoUrls,
    ) {
    }

    /**
     * Imports canonical Shopware storefront routes into their matching
     * SteelCode sales-channel and locale scope.
     *
     * @param array<string, string> $secrets
     * @param callable(): void $ensureActive
     */
    public function import(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
        callable $ensureActive,
    ): void {
        $ensureActive();
        $salesChannels = $this->salesChannels($tenant, $connection, $baseUrl, $secrets);
        $localeIdsByShopwareLanguageId = $this->localeIdsByShopwareLanguageId(
            $tenant,
            $baseUrl,
            $secrets,
        );
        $entityIds = $this->entityIds($connection);
        if ($localeIdsByShopwareLanguageId === [] || $entityIds === []) {
            return;
        }

        $processed = 0;
        $globalPathsImported = [];
        $tenantId = $tenant->getId()->toRfc4122();
        foreach ([
            self::PRODUCT_ROUTE => SeoUrlService::ENTITY_PRODUCT,
            self::CATEGORY_ROUTE => SeoUrlService::ENTITY_CATEGORY,
        ] as $routeName => $entityType) {
            $externalEntityIds = array_keys($entityIds[$entityType] ?? []);
            foreach (array_chunk($externalEntityIds, 500) as $externalEntityIdsChunk) {
                $ensureActive();
                $this->shopwareClient->forEachEntityPage(
                    $baseUrl,
                    $secrets,
                    'seo-url',
                    function (int $total, array $items) use (
                        $tenantId,
                        $salesChannels,
                        $localeIdsByShopwareLanguageId,
                        $entityIds,
                        &$processed,
                        &$globalPathsImported,
                        $ensureActive,
                    ): void {
                        foreach ($items as $item) {
                            $ensureActive();
                            $this->importSeoUrl(
                                $item,
                                $tenantId,
                                $salesChannels,
                                $localeIdsByShopwareLanguageId,
                                $entityIds,
                                $globalPathsImported,
                            );

                            ++$processed;
                            if ($processed % self::BATCH_SIZE === 0) {
                                $this->entityManager->flush();
                                $this->entityManager->clear();
                            }
                        }
                    },
                    criteria: [
                        'filter' => [
                            [
                                'type' => 'equals',
                                'field' => 'isCanonical',
                                'value' => true,
                            ],
                            [
                                'type' => 'equals',
                                'field' => 'isDeleted',
                                'value' => false,
                            ],
                            [
                                'type' => 'equals',
                                'field' => 'routeName',
                                'value' => $routeName,
                            ],
                            [
                                'type' => 'equalsAny',
                                'field' => 'foreignKey',
                                'value' => $externalEntityIdsChunk,
                            ],
                        ],
                    ],
                );
            }
        }

        $ensureActive();
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param array<string, string> $salesChannels Shopware ID => local ID
     * @param array<string, string> $localeIdsByShopwareLanguageId Shopware ID => local ID
     * @param array<string, array<string, string>> $entityIds Entity type => Shopware ID => local ID
     * @param array<string, true> $globalPathsImported
     */
    private function importSeoUrl(
        array $item,
        string $tenantId,
        array $salesChannels,
        array $localeIdsByShopwareLanguageId,
        array $entityIds,
        array &$globalPathsImported,
    ): void {
        $attributes = $this->attributes($item);
        if (
            !(bool) ($attributes['isCanonical'] ?? false)
            || (bool) ($attributes['isDeleted'] ?? false)
        ) {
            return;
        }

        $entityType = match ($attributes['routeName'] ?? null) {
            self::PRODUCT_ROUTE => SeoUrlService::ENTITY_PRODUCT,
            self::CATEGORY_ROUTE => SeoUrlService::ENTITY_CATEGORY,
            default => null,
        };
        $externalEntityId = $this->string($attributes['foreignKey'] ?? null);
        $shopwareLanguageId = $this->string($attributes['languageId'] ?? null);
        $shopwareSalesChannelId = $this->string($attributes['salesChannelId'] ?? null);
        $path = $this->path($attributes['seoPathInfo'] ?? null);
        if (
            $entityType === null
            || $externalEntityId === null
            || $shopwareLanguageId === null
            || $path === null
        ) {
            return;
        }

        $localEntityId = $entityIds[$entityType][$externalEntityId] ?? null;
        $localLocaleId = $localeIdsByShopwareLanguageId[$shopwareLanguageId] ?? null;
        $localSalesChannelId = $shopwareSalesChannelId === null
            ? null
            : ($salesChannels[$shopwareSalesChannelId] ?? null);
        if (
            $localEntityId === null
            || $localLocaleId === null
            || ($shopwareSalesChannelId !== null && $localSalesChannelId === null)
        ) {
            return;
        }

        $tenant = $this->entityManager->getReference(Tenant::class, Uuid::fromString($tenantId));
        $locale = $this->entityManager->getReference(Locale::class, Uuid::fromString($localLocaleId));
        $entityId = Uuid::fromString($localEntityId);
        $globalPathKey = implode('|', [
            $entityType,
            $localEntityId,
            $localLocaleId,
        ]);
        if (!isset($globalPathsImported[$globalPathKey])) {
            $this->seoUrls->syncCanonical(
                $tenant,
                $entityType,
                $entityId,
                $locale,
                $path,
                $path,
                source: 'import',
                modified: (bool) ($attributes['isModified'] ?? false),
            );
            $globalPathsImported[$globalPathKey] = true;
        }

        if ($localSalesChannelId === null) {
            return;
        }

        $salesChannel = $this->entityManager->getReference(
            IntegrationSalesChannel::class,
            Uuid::fromString($localSalesChannelId),
        );
        $this->seoUrls->syncCanonical(
            $tenant,
            $entityType,
            $entityId,
            $locale,
            $path,
            $path,
            $salesChannel,
            'import',
            (bool) ($attributes['isModified'] ?? false),
        );
    }

    /**
     * @param array<string, string> $secrets
     *
     * @return array<string, string> Shopware sales-channel ID => local ID
     */
    private function salesChannels(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
    ): array {
        $localIds = [];
        foreach ($this->shopwareClient->salesChannels($baseUrl, $secrets) as $source) {
            $salesChannel = $this->entityManager
                ->getRepository(IntegrationSalesChannel::class)
                ->findOneBy([
                    'connection' => $connection,
                    'externalId' => $source['id'],
                ]);
            if (!$salesChannel instanceof IntegrationSalesChannel) {
                $salesChannel = new IntegrationSalesChannel(
                    $tenant,
                    $connection,
                    $source['id'],
                    $source['name'],
                    $source['type'],
                    $source['active'],
                    $source['sourceData'],
                );
                $this->entityManager->persist($salesChannel);
            } else {
                $salesChannel->update(
                    $source['name'],
                    $source['type'],
                    $source['active'],
                    $source['sourceData'],
                );
            }

            $localIds[$source['id']] = $salesChannel->getId()->toRfc4122();
        }
        $this->entityManager->flush();

        return $localIds;
    }

    /**
     * @param array<string, string> $secrets
     *
     * @return array<string, string> Shopware language ID => local locale ID
     */
    private function localeIdsByShopwareLanguageId(
        Tenant $tenant,
        string $baseUrl,
        array $secrets,
    ): array {
        $localesByCode = [];
        foreach ($this->entityManager->getRepository(Locale::class)->findBy([
            'code' => $tenant->getEnabledSnippetLocales(),
        ]) as $locale) {
            if ($locale instanceof Locale) {
                $localesByCode[$this->localeKey($locale->getCode())] = $locale->getId()->toRfc4122();
            }
        }

        $localeIds = [];
        foreach ($this->shopwareClient->languageLocaleCodes($baseUrl, $secrets) as $languageId => $code) {
            $localLocaleId = $localesByCode[$this->localeKey($code)] ?? null;
            if ($localLocaleId !== null) {
                $localeIds[$languageId] = $localLocaleId;
            }
        }

        return $localeIds;
    }

    /**
     * @return array<string, array<string, string>> Entity type => Shopware ID => local ID
     */
    private function entityIds(IntegrationConnection $connection): array
    {
        $entityIds = [];
        foreach ($this->entityManager->getRepository(IntegrationEntityMapping::class)->findBy([
            'connection' => $connection,
        ]) as $mapping) {
            if (!$mapping instanceof IntegrationEntityMapping) {
                continue;
            }
            if (!in_array($mapping->getEntityType(), [
                SeoUrlService::ENTITY_PRODUCT,
                SeoUrlService::ENTITY_CATEGORY,
            ], true)) {
                continue;
            }

            $entityIds[$mapping->getEntityType()][$mapping->getExternalId()] = $mapping
                ->getLocalId()
                ->toRfc4122();
        }

        return $entityIds;
    }

    /** @param array<string, mixed> $item */
    private function attributes(array $item): array
    {
        return is_array($item['attributes'] ?? null)
            ? $item['attributes']
            : $item;
    }

    private function path(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $path = trim($value, " /\\t\\n\\r\\0\\x0B");

        return $path === '' ? null : $path;
    }

    private function string(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function localeKey(string $code): string
    {
        return strtolower(str_replace('_', '-', trim($code)));
    }
}
