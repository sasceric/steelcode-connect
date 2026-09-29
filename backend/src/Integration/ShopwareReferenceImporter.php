<?php

namespace App\Integration;

use App\Entity\Category;
use App\Entity\CategoryTranslation;
use App\Entity\Currency;
use App\Entity\CustomField;
use App\Entity\CustomFieldOption;
use App\Entity\CustomFieldSet;
use App\Entity\DeliveryTime;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSecret;
use App\Entity\IntegrationSalesChannel;
use App\Entity\Locale;
use App\Entity\Manufacturer;
use App\Entity\ManufacturerTranslation;
use App\Entity\Media;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\PropertyGroupTranslation;
use App\Entity\PropertyTranslation;
use App\Entity\Tax;
use App\Entity\TaxTranslation;
use App\Entity\Tag;
use App\Entity\Tenant;
use App\Entity\TenantCurrency;
use App\Entity\Unit;
use App\Entity\UnitTranslation;
use App\Service\SeoUrlService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ShopwareReferenceImporter
{
    /** @var null|\Closure */
    private ?\Closure $ensureActive = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretCipher $cipher,
        private readonly ShopwareClient $shopwareClient,
        private readonly SluggerInterface $slugger,
        private readonly SeoUrlService $seoUrls,
    ) {
    }

    /**
     * @param callable(string): void $onStage
     * @param callable(): void $ensureActive
     */
    public function import(
        IntegrationImportRun $run,
        callable $onStage,
        callable $ensureActive,
    ): void
    {
        $this->ensureActive = \Closure::fromCallable($ensureActive);
        $this->checkActive();
        $connection = $run->getConnection();
        $tenant = $run->getTenant();
        $baseUrl = $connection->getConfiguration()['baseUrl'] ?? null;
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            throw new \RuntimeException('The Shopware platform URL is not configured.');
        }

        $locale = $this->defaultLocale($tenant);
        $secrets = $this->secrets($connection);
        $areas = $this->areas($connection);

        try {
            $onStage('salesChannels');
            $this->syncSalesChannels($tenant, $connection, $baseUrl, $secrets);
            $this->entityManager->flush();

            $onStage('currencies');
            $this->syncCurrencies($tenant, $baseUrl, $secrets);
            $this->entityManager->flush();

            if ($areas['units']) {
                $onStage('units');
                $this->syncUnits($tenant, $connection, $locale, $baseUrl, $secrets);
                $this->entityManager->flush();
            }

            if ($areas['tags']) {
                $onStage('tags');
                $this->syncTags($tenant, $connection, $baseUrl, $secrets);
                $this->entityManager->flush();
            }

            if ($areas['taxes']) {
                $onStage('taxes');
                $this->syncTaxes($tenant, $connection, $locale, $baseUrl, $secrets);
                $this->entityManager->flush();
            }

            if ($areas['deliveryTimes']) {
                $onStage('deliveryTimes');
                $this->syncDeliveryTimes($tenant, $connection, $locale, $baseUrl, $secrets);
                $this->entityManager->flush();
            }

            if ($areas['manufacturers']) {
                $onStage('manufacturers');
                $this->syncManufacturers($tenant, $connection, $locale, $baseUrl, $secrets);
                $this->entityManager->flush();
            }

            if ($areas['properties']) {
                $onStage('properties');
                $this->syncProperties($tenant, $connection, $locale, $baseUrl, $secrets);
                $this->entityManager->flush();
            }

            if ($areas['customFields']) {
                $onStage('customFields');
                $this->syncCustomFields($tenant, $connection, $locale, $baseUrl, $secrets);
                $this->entityManager->flush();
            }

            if ($areas['categories']) {
                $onStage('categories');
                $this->syncCategories($tenant, $connection, $locale, $baseUrl, $secrets);
                $this->entityManager->flush();
            }

            $this->checkActive();
            $onStage('translations');
            $this->syncTranslations(
                $tenant,
                $connection,
                $baseUrl,
                $secrets,
                $areas,
            );
            $this->entityManager->flush();
        } finally {
            $this->ensureActive = null;
        }
    }

    private function checkActive(): void
    {
        if ($this->ensureActive !== null) {
            ($this->ensureActive)();
        }
    }

    /**
     * @param array<string, string> $secrets
     * @param array<string, bool> $areas
     */
    private function syncTranslations(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
        array $areas,
    ): void {
        $connectionId = $connection->getId();
        $tenantId = $tenant->getId();

        foreach ($this->translationLocales($tenant, $baseUrl, $secrets) as $languageId => $localeId) {
            $this->checkActive();
            $tenant = $this->entityManager->find(Tenant::class, $tenantId);
            $connection = $this->entityManager->find(IntegrationConnection::class, $connectionId);
            $locale = $this->entityManager->find(Locale::class, $localeId);
            if (
                !$tenant instanceof Tenant
                || !$connection instanceof IntegrationConnection
                || !$locale instanceof Locale
            ) {
                throw new \RuntimeException('The import context could not be reloaded.');
            }

            if ($areas['taxes']) {
                $this->syncTaxes(
                    $tenant,
                    $connection,
                    $locale,
                    $baseUrl,
                    $secrets,
                    $languageId,
                );
            }
            if ($areas['units']) {
                $this->syncUnits(
                    $tenant,
                    $connection,
                    $locale,
                    $baseUrl,
                    $secrets,
                    $languageId,
                );
            }
            if ($areas['deliveryTimes']) {
                $this->syncDeliveryTimes(
                    $tenant,
                    $connection,
                    $locale,
                    $baseUrl,
                    $secrets,
                    $languageId,
                );
            }
            if ($areas['manufacturers']) {
                $this->syncManufacturers(
                    $tenant,
                    $connection,
                    $locale,
                    $baseUrl,
                    $secrets,
                    $languageId,
                );
            }
            if ($areas['properties']) {
                $this->syncProperties(
                    $tenant,
                    $connection,
                    $locale,
                    $baseUrl,
                    $secrets,
                    $languageId,
                );
            }
            if ($areas['customFields']) {
                $this->syncCustomFields(
                    $tenant,
                    $connection,
                    $locale,
                    $baseUrl,
                    $secrets,
                    $languageId,
                );
            }
            if ($areas['categories']) {
                $this->syncCategories(
                    $tenant,
                    $connection,
                    $locale,
                    $baseUrl,
                    $secrets,
                    $languageId,
                );
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }
    }

    /** @param array<string, string> $secrets */
    private function syncCurrencies(Tenant $tenant, string $baseUrl, array $secrets): void
    {
        foreach ($this->shopwareClient->currencyCodes($baseUrl, $secrets) as $code) {
            $currency = $this->entityManager->getRepository(Currency::class)->findOneBy([
                'code' => $code,
            ]);
            if (!$currency instanceof Currency) {
                $currency = new Currency($code, $this->currencySymbol($code), 2);
                $this->entityManager->persist($currency);
            }

            $tenantCurrency = $this->entityManager
                ->getRepository(TenantCurrency::class)
                ->findOneBy([
                    'tenant' => $tenant,
                    'currency' => $currency,
                ]);
            if (!$tenantCurrency instanceof TenantCurrency) {
                $this->entityManager->persist(new TenantCurrency($tenant, $currency));
            }
        }
    }

    /** @param array<string, string> $secrets */
    private function syncSalesChannels(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
    ): void {
        foreach ($this->shopwareClient->salesChannels($baseUrl, $secrets) as $source) {
            $this->checkActive();
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

                continue;
            }

            $salesChannel->update(
                $source['name'],
                $source['type'],
                $source['active'],
                $source['sourceData'],
            );
        }
    }

    /** @param array<string, string> $secrets */
    private function syncUnits(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        string $baseUrl,
        array $secrets,
        ?string $languageId = null,
    ): void {
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'unit',
            function (int $total, array $items) use ($tenant, $connection, $locale): void {
                foreach ($items as $source) {
                    $this->checkActive();
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $name = $this->name($attributes);
                    if ($externalId === null || $name === null) {
                        continue;
                    }

                    $shortCode = $this->string($attributes['shortCode'] ?? null);
                    $code = strtolower($shortCode ?? 'shopware_'.substr($externalId, 0, 24));
                    $symbol = $shortCode ?? $name;
                    $unit = $this->mappedEntity(
                        $connection,
                        'unit',
                        $externalId,
                        Unit::class,
                        $tenant,
                    );
                    if (!$unit instanceof Unit) {
                        $unit = $this->entityManager->getRepository(Unit::class)->findOneBy([
                            'tenant' => $tenant,
                            'code' => $code,
                        ]);
                    }
                    $labels = $this->mergeLocalizedLabels(
                        $unit instanceof Unit ? $unit->getLabels() : [],
                        [$locale->getCode() => $name],
                    );
                    if (!$unit instanceof Unit) {
                        $unit = new Unit($tenant, $code, $symbol, $labels);
                        $this->entityManager->persist($unit);
                    }
                    $unit->update(
                        $code,
                        $symbol,
                        $labels,
                        (bool) ($attributes['active'] ?? true),
                    );
                    $this->ensureMapping($tenant, $connection, 'unit', $externalId, $unit);

                    $translation = $this->entityManager
                        ->getRepository(UnitTranslation::class)
                        ->findOneBy(['unit' => $unit, 'locale' => $locale]);
                    $translation ??= new UnitTranslation($unit, $locale, $name);
                    $translation->update($name);
                    $this->entityManager->persist($translation);
                }
            },
            languageId: $languageId,
        );
    }

    /** @param array<string, string> $secrets */
    private function syncTags(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
    ): void {
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'tag',
            function (int $total, array $items) use ($tenant, $connection): void {
                foreach ($items as $source) {
                    $this->checkActive();
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $name = $this->name($attributes);
                    if ($externalId === null || $name === null) {
                        continue;
                    }

                    $tag = $this->mappedEntity(
                        $connection,
                        'tag',
                        $externalId,
                        Tag::class,
                        $tenant,
                    );
                    if (!$tag instanceof Tag) {
                        $tag = $this->entityManager->getRepository(Tag::class)->findOneBy([
                            'tenant' => $tenant,
                            'name' => $name,
                        ]);
                    }
                    if (!$tag instanceof Tag) {
                        $tag = new Tag($tenant, $name);
                        $this->entityManager->persist($tag);
                    }
                    $tag->update($name);
                    $this->ensureMapping($tenant, $connection, 'tag', $externalId, $tag);
                }
            },
        );
    }

    /** @param array<string, string> $secrets */
    private function syncTaxes(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        string $baseUrl,
        array $secrets,
        ?string $languageId = null,
    ): void {
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'tax',
            function (int $total, array $items) use ($tenant, $connection, $locale, $languageId): void {
                foreach ($items as $source) {
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $rate = $this->decimal($attributes['taxRate'] ?? null);
                    if ($externalId === null || $rate === null) {
                        continue;
                    }

                    $name = $this->name($attributes)
                        ?? sprintf('VAT %s%%', rtrim(rtrim($rate, '0'), '.'));
                    $tax = $this->mappedEntity($connection, 'tax', $externalId, Tax::class, $tenant);
                    if (!$tax instanceof Tax) {
                        $tax = $this->entityManager->getRepository(Tax::class)->findOneBy([
                            'tenant' => $tenant,
                            'name' => $name,
                        ]);
                    }
                    if (!$tax instanceof Tax) {
                        $tax = new Tax($tenant, $name, $rate);
                        $this->entityManager->persist($tax);
                    }
                    if ($languageId === null) {
                        $tax->update($name, $rate, (bool) ($attributes['active'] ?? true));
                    }
                    $this->ensureMapping($tenant, $connection, 'tax', $externalId, $tax);

                    $translation = $this->entityManager
                        ->getRepository(TaxTranslation::class)
                        ->findOneBy(['tax' => $tax, 'locale' => $locale]);
                    $translation ??= new TaxTranslation($tax, $locale, $name);
                    $translation->update($name);
                    $this->entityManager->persist($translation);
                }
            },
            languageId: $languageId,
        );
    }

    /** @param array<string, string> $secrets */
    private function syncDeliveryTimes(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        string $baseUrl,
        array $secrets,
        ?string $languageId = null,
    ): void {
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'delivery-time',
            function (int $total, array $items) use ($tenant, $connection, $locale, $languageId): void {
                foreach ($items as $source) {
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $name = $this->name($attributes);
                    if ($externalId === null || $name === null) {
                        continue;
                    }

                    $deliveryTime = $this->mappedEntity(
                        $connection,
                        'delivery_time',
                        $externalId,
                        DeliveryTime::class,
                        $tenant,
                    );
                    $labels = $this->mergeLocalizedLabels(
                        $deliveryTime instanceof DeliveryTime ? $deliveryTime->getLabels() : [],
                        [$locale->getCode() => $name],
                    );
                    $min = max(0, (int) ($attributes['min'] ?? 0));
                    $max = max($min, (int) ($attributes['max'] ?? $min));
                    $unit = $this->string($attributes['unit'] ?? null) ?? 'day';
                    if (!$deliveryTime instanceof DeliveryTime) {
                        $deliveryTime = new DeliveryTime($tenant, $labels, $min, $max, $unit);
                        $this->entityManager->persist($deliveryTime);
                    }
                    $deliveryTime->update(
                        $labels,
                        $min,
                        $max,
                        $unit,
                        (bool) ($attributes['active'] ?? true),
                    );
                    $this->ensureMapping(
                        $tenant,
                        $connection,
                        'delivery_time',
                        $externalId,
                        $deliveryTime,
                    );
                }
            },
            languageId: $languageId,
        );
    }

    /** @param array<string, string> $secrets */
    private function syncManufacturers(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        string $baseUrl,
        array $secrets,
        ?string $languageId = null,
    ): void {
        $processedManufacturers = [];

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'product-manufacturer',
            function (int $total, array $items) use (
                $tenant,
                $connection,
                $locale,
                $languageId,
                &$processedManufacturers,
            ): void {
                foreach ($items as $source) {
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $name = $this->name($attributes);
                    if ($externalId === null || $name === null) {
                        continue;
                    }

                    $manufacturer = $this->mappedEntity(
                        $connection,
                        'manufacturer',
                        $externalId,
                        Manufacturer::class,
                        $tenant,
                    );
                    if (!$manufacturer instanceof Manufacturer) {
                        $translation = $this->entityManager
                            ->getRepository(ManufacturerTranslation::class)
                            ->findOneBy(['locale' => $locale, 'name' => $name]);
                        $manufacturer = $translation?->getManufacturer();
                    }
                    if (!$manufacturer instanceof Manufacturer) {
                        $manufacturer = new Manufacturer($tenant);
                        $this->entityManager->persist($manufacturer);
                    }
                    if ($languageId === null) {
                        $sourceMediaId = $this->string($attributes['mediaId'] ?? null);
                        $media = $sourceMediaId === null
                            ? null
                            : $this->mappedEntity(
                                $connection,
                                'media',
                                $sourceMediaId,
                                Media::class,
                                $tenant,
                            );
                        $manufacturer->update(
                            $this->string($attributes['link'] ?? null),
                            $media instanceof Media ? $media : null,
                        );
                    }
                    $this->ensureMapping(
                        $tenant,
                        $connection,
                        'manufacturer',
                        $externalId,
                        $manufacturer,
                    );

                    $manufacturerId = $manufacturer->getId()->toRfc4122();
                    if (isset($processedManufacturers[$manufacturerId])) {
                        continue;
                    }
                    $processedManufacturers[$manufacturerId] = true;

                    $translation = $this->entityManager
                        ->getRepository(ManufacturerTranslation::class)
                        ->findOneBy(['manufacturer' => $manufacturer, 'locale' => $locale]);
                    $translation ??= new ManufacturerTranslation($manufacturer, $locale, $name);
                    $translation->update(
                        $name,
                        $this->string($attributes['description'] ?? null),
                        null,
                        null,
                        null,
                        is_array($attributes['customFields'] ?? null)
                            ? $attributes['customFields']
                            : [],
                    );
                    $this->entityManager->persist($translation);
                    $this->seoUrls->syncCanonical(
                        $tenant,
                        SeoUrlService::ENTITY_MANUFACTURER,
                        $manufacturer->getId(),
                        $locale,
                        $name,
                        source: 'import',
                    );
                }
            },
            languageId: $languageId,
        );
    }

    /** @param array<string, string> $secrets */
    private function syncProperties(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        string $baseUrl,
        array $secrets,
        ?string $languageId = null,
    ): void {
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'property-group',
            function (int $total, array $items) use ($tenant, $connection, $locale, $languageId): void {
                foreach ($items as $source) {
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $name = $this->name($attributes);
                    if ($externalId === null || $name === null) {
                        continue;
                    }

                    $group = $this->mappedEntity(
                        $connection,
                        'property_group',
                        $externalId,
                        PropertyGroup::class,
                        $tenant,
                    );
                    $code = 'shopware_'.substr($externalId, 0, 24);
                    if (!$group instanceof PropertyGroup) {
                        $group = new PropertyGroup(
                            $tenant,
                            $name,
                            $code,
                            $this->propertyDisplayType($attributes['displayType'] ?? null),
                            (bool) ($attributes['filterable'] ?? true),
                            (bool) ($attributes['displayOnProductDetail'] ?? true),
                            $this->propertySorting($attributes['sortingType'] ?? null),
                            max(0, (int) ($attributes['position'] ?? 0)),
                        );
                        $this->entityManager->persist($group);
                    }
                    if ($languageId === null) {
                        $group->update(
                            $name,
                            $code,
                            $this->propertyDisplayType($attributes['displayType'] ?? null),
                            (bool) ($attributes['filterable'] ?? true),
                            (bool) ($attributes['displayOnProductDetail'] ?? true),
                            $this->propertySorting($attributes['sortingType'] ?? null),
                            max(0, (int) ($attributes['position'] ?? 0)),
                        );
                    }
                    $this->ensureMapping(
                        $tenant,
                        $connection,
                        'property_group',
                        $externalId,
                        $group,
                    );

                    $translation = $this->entityManager
                        ->getRepository(PropertyGroupTranslation::class)
                        ->findOneBy(['propertyGroup' => $group, 'locale' => $locale]);
                    $translation ??= new PropertyGroupTranslation($group, $locale, $name);
                    $translation->update($name);
                    $this->entityManager->persist($translation);
                }
            },
            languageId: $languageId,
        );

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'property-group-option',
            function (int $total, array $items) use ($tenant, $connection, $locale, $languageId): void {
                foreach ($items as $position => $source) {
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $name = $this->name($attributes);
                    $groupExternalId = $this->string($attributes['groupId'] ?? null);
                    if ($externalId === null || $name === null || $groupExternalId === null) {
                        continue;
                    }

                    $group = $this->mappedEntity(
                        $connection,
                        'property_group',
                        $groupExternalId,
                        PropertyGroup::class,
                        $tenant,
                    );
                    if (!$group instanceof PropertyGroup) {
                        continue;
                    }

                    $property = $this->mappedEntity(
                        $connection,
                        'property',
                        $externalId,
                        Property::class,
                        $tenant,
                    );
                    $code = 'shopware_'.substr($externalId, 0, 24);
                    $colorHex = $this->color($attributes['colorHexCode'] ?? null);
                    $propertyPosition = max(0, (int) ($attributes['position'] ?? $position));
                    if (!$property instanceof Property) {
                        $property = new Property(
                            $tenant,
                            $group,
                            $name,
                            $code,
                            $colorHex,
                            $propertyPosition,
                        );
                        $this->entityManager->persist($property);
                    }
                    if ($languageId === null) {
                        $sourceMediaId = $this->string($attributes['mediaId'] ?? null);
                        $media = $sourceMediaId === null
                            ? null
                            : $this->mappedEntity(
                                $connection,
                                'media',
                                $sourceMediaId,
                                Media::class,
                                $tenant,
                            );
                        $property->update(
                            $name,
                            $code,
                            $colorHex,
                            $propertyPosition,
                            $media instanceof Media ? $media : null,
                        );
                    }
                    $this->ensureMapping(
                        $tenant,
                        $connection,
                        'property',
                        $externalId,
                        $property,
                    );

                    $translation = $this->entityManager
                        ->getRepository(PropertyTranslation::class)
                        ->findOneBy(['property' => $property, 'locale' => $locale]);
                    $translation ??= new PropertyTranslation($property, $locale, $name);
                    $translation->update($name);
                    $this->entityManager->persist($translation);
                }
            },
            languageId: $languageId,
        );
    }

    /** @param array<string, string> $secrets */
    private function syncCustomFields(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        string $baseUrl,
        array $secrets,
        ?string $languageId = null,
    ): void {
        /** @var array<string, list<string>> $relationsBySetExternalId */
        $relationsBySetExternalId = [];
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'custom-field-set-relation',
            function (int $total, array $items) use (&$relationsBySetExternalId): void {
                foreach ($items as $source) {
                    $attributes = $this->attributes($source);
                    $setExternalId = $this->string($attributes['customFieldSetId'] ?? null);
                    $relation = $this->relation($attributes['entityName'] ?? null);
                    if ($setExternalId === null || $relation === null) {
                        continue;
                    }
                    $relationsBySetExternalId[$setExternalId] ??= [];
                    $relationsBySetExternalId[$setExternalId][] = $relation;
                }
            },
            languageId: $languageId,
        );
        foreach ($relationsBySetExternalId as $setExternalId => $relations) {
            $relationsBySetExternalId[$setExternalId] = array_values(array_unique($relations));
        }

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'custom-field-set',
            function (int $total, array $items) use ($tenant, $connection, $locale, $relationsBySetExternalId): void {
                foreach ($items as $source) {
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $technicalName = $this->technical($attributes['name'] ?? null);
                    if ($externalId === null || $technicalName === '') {
                        continue;
                    }

                    $labels = $this->labels($attributes['config'] ?? [], $locale, $technicalName);
                    $relations = $relationsBySetExternalId[$externalId] ?? [];
                    $set = $this->mappedEntity(
                        $connection,
                        'custom_field_set',
                        $externalId,
                        CustomFieldSet::class,
                        $tenant,
                    );
                    if (!$set instanceof CustomFieldSet) {
                        $set = $this->entityManager->getRepository(CustomFieldSet::class)
                            ->findOneBy([
                                'tenant' => $tenant,
                                'technicalName' => $technicalName,
                            ]);
                    }
                    if (!$set instanceof CustomFieldSet) {
                        $set = new CustomFieldSet(
                            $tenant,
                            $technicalName,
                            $labels,
                            $relations,
                            max(0, (int) ($attributes['position'] ?? 0)),
                        );
                        $this->entityManager->persist($set);
                    }
                    $set->update(
                        $technicalName,
                        $this->mergeLocalizedLabels($set->getLabels(), $labels),
                        $relations,
                        max(0, (int) ($attributes['position'] ?? 0)),
                    );
                    $this->ensureMapping(
                        $tenant,
                        $connection,
                        'custom_field_set',
                        $externalId,
                        $set,
                    );

                    foreach ($this->relatedItems($attributes, 'customFields') as $position => $fieldSource) {
                        $this->syncCustomField(
                            $tenant,
                            $connection,
                            $locale,
                            $set,
                            $fieldSource,
                            $position,
                        );
                    }
                }
            },
            [
                'customFields' => [],
            ],
            languageId: $languageId,
        );

        $this->entityManager->flush();

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'custom-field',
            function (int $total, array $items) use ($tenant, $connection, $locale): void {
                foreach ($items as $position => $source) {
                    $attributes = $this->attributes($source);
                    $setExternalId = $this->string(
                        $attributes['setId']
                        ?? $attributes['customFieldSetId']
                        ?? null,
                    );
                    $set = null;
                    if ($setExternalId !== null) {
                        $set = $this->mappedEntity(
                            $connection,
                            'custom_field_set',
                            $setExternalId,
                            CustomFieldSet::class,
                            $tenant,
                        );
                        if (!$set instanceof CustomFieldSet) {
                            continue;
                        }
                    }

                    $this->syncCustomField(
                        $tenant,
                        $connection,
                        $locale,
                        $set,
                        $source,
                        $position,
                    );
                }
            },
            languageId: $languageId,
        );
    }

    /** @param array<string, mixed> $source */
    private function syncCustomField(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        ?CustomFieldSet $set,
        array $source,
        int $position,
    ): void {
        $attributes = $this->attributes($source);
        $externalId = $this->externalId($source, $attributes);
        $technicalName = $this->technical($attributes['name'] ?? null);
        if ($externalId === null || $technicalName === '') {
            return;
        }

        $field = $this->mappedEntity(
            $connection,
            'custom_field',
            $externalId,
            CustomField::class,
            $tenant,
        );
        if (!$field instanceof CustomField) {
            $field = $this->entityManager->getRepository(CustomField::class)
                ->findOneBy([
                    'tenant' => $tenant,
                    'customFieldSet' => $set,
                    'technicalName' => $technicalName,
                ]);
        }

        $config = is_array($attributes['config'] ?? null)
            ? $attributes['config']
            : [];
        $labels = $this->labels($config, $locale, $technicalName);
        $fieldPosition = max(0, (int) ($attributes['position'] ?? $position));
        if (!$field instanceof CustomField) {
            $field = new CustomField(
                $tenant,
                $set,
                $technicalName,
                $this->customFieldType($attributes['type'] ?? null),
                $labels,
                $config,
                $fieldPosition,
            );
            $this->entityManager->persist($field);
        }
        $field->update(
            $technicalName,
            $this->customFieldType($attributes['type'] ?? null),
            $this->mergeLocalizedLabels($field->getLabels(), $labels),
            $config,
            $fieldPosition,
        );
        $this->ensureMapping(
            $tenant,
            $connection,
            'custom_field',
            $externalId,
            $field,
        );
        $this->syncCustomFieldOptions($field, $config, $locale);
    }

    /** @param array<string, string> $secrets */
    private function syncCategories(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        string $baseUrl,
        array $secrets,
        ?string $languageId = null,
    ): void {
        $sourceCategories = [];
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'category',
            function (int $total, array $items) use (&$sourceCategories): void {
                foreach ($items as $source) {
                    $sourceCategories[] = $source;
                }
            },
            languageId: $languageId,
        );

        foreach ($sourceCategories as $source) {
            $attributes = $this->attributes($source);
            $externalId = $this->externalId($source, $attributes);
            $name = $this->name($attributes);
            if ($externalId === null || $name === null) {
                continue;
            }

            $category = $this->mappedEntity(
                $connection,
                'category',
                $externalId,
                Category::class,
                $tenant,
            );
            if (!$category instanceof Category) {
                $category = new Category($tenant, max(0, (int) ($attributes['afterCategoryId'] ?? 0)));
                $this->entityManager->persist($category);
            }
            $sourceMediaId = $this->string($attributes['mediaId'] ?? null);
            $media = $sourceMediaId === null
                ? null
                : $this->mappedEntity(
                    $connection,
                    'media',
                    $sourceMediaId,
                    Media::class,
                    $tenant,
                );
            $category->update(
                (bool) ($attributes['active'] ?? true),
                (bool) ($attributes['visible'] ?? true),
                $media instanceof Media ? $media : null,
            );
            $this->ensureMapping($tenant, $connection, 'category', $externalId, $category);

            $translation = $this->entityManager
                ->getRepository(CategoryTranslation::class)
                ->findOneBy(['category' => $category, 'locale' => $locale]);
            $translation ??= new CategoryTranslation($category, $locale, $name);
            $translation->update(
                $name,
                $this->string($attributes['description'] ?? null),
                $this->string($attributes['metaTitle'] ?? null),
                $this->string($attributes['metaDescription'] ?? null),
                $this->string($attributes['keywords'] ?? null),
                is_array($attributes['customFields'] ?? null)
                    ? $attributes['customFields']
                    : [],
            );
            $this->entityManager->persist($translation);
            $this->seoUrls->syncCanonical(
                $tenant,
                SeoUrlService::ENTITY_CATEGORY,
                $category->getId(),
                $locale,
                $name,
                source: 'import',
            );
        }
        $this->entityManager->flush();

        foreach ($sourceCategories as $source) {
            $attributes = $this->attributes($source);
            $externalId = $this->externalId($source, $attributes);
            if ($externalId === null) {
                continue;
            }
            $category = $this->mappedEntity(
                $connection,
                'category',
                $externalId,
                Category::class,
                $tenant,
            );
            if (!$category instanceof Category) {
                continue;
            }
            $parentId = $this->string($attributes['parentId'] ?? null);
            $parent = $parentId === null
                ? null
                : $this->mappedEntity($connection, 'category', $parentId, Category::class, $tenant);
            $category->move(
                $parent instanceof Category ? $parent : null,
                max(0, (int) ($attributes['position'] ?? 0)),
            );
        }
    }

    /** @param array<string, mixed> $config */
    private function syncCustomFieldOptions(
        CustomField $field,
        array $config,
        Locale $locale,
    ): void {
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];
        foreach ($options as $position => $option) {
            if (!is_array($option)) {
                continue;
            }
            $value = $this->technical($option['value'] ?? null);
            if ($value === '') {
                continue;
            }
            $labels = $this->optionLabels($option['label'] ?? null, $locale, $value);
            $existing = $this->entityManager->getRepository(CustomFieldOption::class)
                ->findOneBy([
                    'customField' => $field,
                    'technicalValue' => $value,
                ]);
            if (!$existing instanceof CustomFieldOption) {
                $existing = new CustomFieldOption($field, $value, $labels, $position);
                $this->entityManager->persist($existing);
                continue;
            }
            $existing->update(
                $value,
                $this->mergeLocalizedLabels($existing->getLabels(), $labels),
                $position,
            );
        }
    }

    /** @return array<string, bool> */
    private function areas(IntegrationConnection $connection): array
    {
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        $configuredAreas = is_array($settings)
            && is_array($settings['areas'] ?? null)
            ? $settings['areas']
            : [];
        $defaults = [
            'units' => true,
            'tags' => true,
            'taxes' => true,
            'deliveryTimes' => true,
            'manufacturers' => true,
            'properties' => true,
            'customFields' => true,
            'categories' => true,
        ];
        foreach ($defaults as $key => $default) {
            $defaults[$key] = is_bool($configuredAreas[$key] ?? null)
                ? $configuredAreas[$key]
                : $default;
        }

        return $defaults;
    }

    /** @return array<string, string> */
    private function secrets(IntegrationConnection $connection): array
    {
        $values = [];
        foreach ($this->entityManager->getRepository(IntegrationSecret::class)
            ->findBy(['connection' => $connection]) as $secret) {
            $values[$secret->getSecretKey()] = $this->cipher->decrypt(
                $secret->getCiphertext(),
                $secret->getNonce(),
            );
        }

        return $values;
    }

    private function defaultLocale(Tenant $tenant): Locale
    {
        $locale = $this->entityManager->getRepository(Locale::class)->findOneBy([
            'code' => $tenant->getDefaultSnippetLocale(),
        ]);
        if (!$locale instanceof Locale) {
            throw new \RuntimeException('The tenant default translation language is missing.');
        }

        return $locale;
    }

    /**
     * @param array<string, string> $secrets
     *
     * @return array<string, Uuid> Shopware language ID => local locale ID
     */
    private function translationLocales(
        Tenant $tenant,
        string $baseUrl,
        array $secrets,
    ): array {
        $localesByCode = [];
        foreach ($this->entityManager->getRepository(Locale::class)->findBy([
            'code' => $tenant->getEnabledSnippetLocales(),
        ]) as $locale) {
            if ($locale instanceof Locale) {
                $localesByCode[$this->localeKey($locale->getCode())] = $locale;
            }
        }

        $translationLocales = [];
        foreach ($this->shopwareClient->languageLocaleCodes($baseUrl, $secrets) as $languageId => $code) {
            $locale = $localesByCode[$this->localeKey($code)] ?? null;
            if ($locale instanceof Locale) {
                $translationLocales[$languageId] = $locale->getId();
            }
        }

        return $translationLocales;
    }

    /** @param array<string, mixed> $source */
    private function attributes(array $source): array
    {
        return is_array($source['attributes'] ?? null)
            ? $source['attributes']
            : $source;
    }

    /** @param array<string, mixed> $source */
    private function externalId(array $source, array $attributes): ?string
    {
        return $this->string($source['id'] ?? $attributes['id'] ?? null);
    }

    /** @param array<string, mixed> $attributes */
    private function name(array $attributes): ?string
    {
        $translated = is_array($attributes['translated'] ?? null)
            ? $attributes['translated']
            : [];

        return $this->string($translated['name'] ?? $attributes['name'] ?? null);
    }

    /** @param array<string, mixed> $attributes @return list<array<string, mixed>> */
    private function relatedItems(array $attributes, string $key): array
    {
        $items = $attributes[$key] ?? [];
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            $items,
            static fn (mixed $item): bool => is_array($item),
        ));
    }

    private function mappedEntity(
        IntegrationConnection $connection,
        string $type,
        string $externalId,
        string $class,
        Tenant $tenant,
    ): ?object {
        $mapping = $this->entityManager->getRepository(IntegrationEntityMapping::class)
            ->findOneBy([
                'connection' => $connection,
                'entityType' => $type,
                'externalId' => $externalId,
            ]);
        if (!$mapping instanceof IntegrationEntityMapping) {
            return null;
        }

        $entity = $this->entityManager->getRepository($class)->findOneBy([
            'id' => $mapping->getLocalId(),
            'tenant' => $tenant,
        ]);

        return is_object($entity) ? $entity : null;
    }

    private function ensureMapping(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $type,
        string $externalId,
        object $entity,
    ): void {
        $mapping = $this->entityManager->getRepository(IntegrationEntityMapping::class)
            ->findOneBy([
                'connection' => $connection,
                'entityType' => $type,
                'externalId' => $externalId,
            ]);
        if (!$mapping instanceof IntegrationEntityMapping) {
            $this->entityManager->persist(new IntegrationEntityMapping(
                $tenant,
                $connection,
                $type,
                $externalId,
                $entity->getId(),
            ));

            return;
        }

        if ($mapping->getLocalId()->toRfc4122() !== $entity->getId()->toRfc4122()) {
            $mapping->remap($entity->getId());
        }
    }

    private function string(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function localeKey(string $code): string
    {
        return strtolower(str_replace('_', '-', trim($code)));
    }

    /**
     * @param array<string, string> $current
     * @param array<string, string> $updates
     *
     * @return array<string, string>
     */
    private function mergeLocalizedLabels(array $current, array $updates): array
    {
        return array_replace($current, $updates);
    }

    private function decimal(mixed $value): ?string
    {
        if (!is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function code(string $value): string
    {
        return strtolower($this->slugger->slug($value)->toString()) ?: 'shopware';
    }

    private function technical(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return trim(preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '', '_');
    }

    private function color(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }

        return strtoupper($value);
    }

    private function propertyDisplayType(mixed $value): string
    {
        return match ($value) {
            'color' => 'color',
            'media' => 'image',
            'select' => 'dropdown',
            default => 'text',
        };
    }

    private function propertySorting(mixed $value): string
    {
        return $value === 'alphanumeric' ? 'alphanumeric' : 'custom';
    }

    private function customFieldType(mixed $value): string
    {
        return match ($value) {
            'html' => 'editor',
            'int', 'float' => 'number',
            'bool' => 'switch',
            'date', 'datetime' => 'date',
            'select', 'multi-select' => 'select',
            'entity' => 'entity',
            'media' => 'media',
            'colorpicker' => 'color',
            'price' => 'price',
            default => 'text',
        };
    }

    /** @return array<string, string> */
    private function labels(mixed $config, Locale $locale, string $fallback): array
    {
        if (!is_array($config)) {
            return [$locale->getCode() => $fallback];
        }

        return $this->optionLabels($config['label'] ?? null, $locale, $fallback);
    }

    /** @return array<string, string> */
    private function optionLabels(mixed $value, Locale $locale, string $fallback): array
    {
        if (is_string($value) && trim($value) !== '') {
            return [$locale->getCode() => trim($value)];
        }
        if (is_array($value)) {
            $labels = [];
            foreach ($value as $code => $label) {
                if (is_string($code) && is_string($label) && trim($label) !== '') {
                    $labels[$code] = trim($label);
                }
            }
            if ($labels !== []) {
                return $labels;
            }
        }

        return [$locale->getCode() => $fallback];
    }

    private function relation(mixed $entityName): ?string
    {
        $map = [
            'product' => 'product',
            'category' => 'category',
            'product_manufacturer' => 'manufacturer',
            'customer' => 'customer',
            'order' => 'order',
            'property_group' => 'property_group',
            'property' => 'property',
            'media' => 'media',
        ];

        return $map[$this->string($entityName) ?? ''] ?? null;
    }

    private function currencySymbol(string $code): string
    {
        return [
            'BAM' => 'KM',
            'CHF' => 'CHF',
            'EUR' => '€',
            'GBP' => '£',
            'TRY' => '₺',
            'USD' => '$',
        ][$code] ?? $code;
    }
}
