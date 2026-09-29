<?php

namespace App\Integration;

use App\Entity\Currency;
use App\Entity\Category;
use App\Entity\CategoryProduct;
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
use App\Entity\Product;
use App\Entity\ProductChannelPublication;
use App\Entity\ProductMedia;
use App\Entity\ProductPrice;
use App\Entity\ProductPropertyAssignment;
use App\Entity\ProductTag;
use App\Entity\ProductTranslation;
use App\Entity\Property;
use App\Entity\Tax;
use App\Entity\Tag;
use App\Entity\Tenant;
use App\Entity\TenantCurrency;
use App\Entity\Unit;
use App\Service\InventoryService;
use App\Service\SeoUrlService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ShopwareProductImporter
{
    private const BATCH_SIZE = 25;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretCipher $cipher,
        private readonly ShopwareClient $shopwareClient,
        private readonly InventoryService $inventoryService,
        private readonly SeoUrlService $seoUrls,
        private readonly ShopwareSeoUrlImporter $seoUrlImporter,
    ) {
    }

    /**
     * @param callable(int): void $onStart
     * @param callable(bool, ?string, ?string): void $onItem
     * @param callable(string): void $onStage
     * @param callable(): void $ensureActive
     */
    public function import(
        IntegrationImportRun $run,
        callable $onStart,
        callable $onItem,
        callable $onStage,
        callable $ensureActive,
    ): void {
        $ensureActive();
        $connection = $run->getConnection();
        $tenant = $run->getTenant();
        $baseUrl = $connection->getConfiguration()['baseUrl'] ?? null;
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            throw new \RuntimeException('The Shopware platform URL is not configured.');
        }

        $locale = $this->defaultLocale($tenant);
        $secrets = $this->secrets($connection);
        $currencyCodes = $this->shopwareClient->currencyCodes($baseUrl, $secrets);
        $areas = $this->areas($connection);
        $productMatchOrder = $this->productMatchOrder($connection);
        $connectionId = $connection->getId();
        $tenantId = $tenant->getId();
        $localeId = $locale->getId();

        $processedInBatch = 0;
        $completeBatch = function () use (
            &$processedInBatch,
            &$connection,
            &$tenant,
            &$locale,
            $connectionId,
            $tenantId,
            $localeId,
            $ensureActive,
        ): void {
            $ensureActive();
            ++$processedInBatch;
            if ($processedInBatch % self::BATCH_SIZE !== 0) {
                return;
            }

            $this->entityManager->clear();
            [$connection, $tenant, $locale] = $this->reloadContext(
                $connectionId,
                $tenantId,
                $localeId,
            );
        };

        $ensureActive();
        $onStart($this->shopwareClient->productTotal($baseUrl, $secrets));
        $ensureActive();
        $productMediaByProductId = $this->shopwareClient->productMediaByProductId(
            $baseUrl,
            $secrets,
        );
        $productVisibilitiesByProductId = $areas['channelPublications']
            ? $this->shopwareClient->productVisibilitiesByProductId($baseUrl, $secrets)
            : [];

        $this->shopwareClient->forEachProductPage(
            $baseUrl,
            $secrets,
            function (int $total, array $products) use (
                &$connection,
                &$tenant,
                &$locale,
                $currencyCodes,
                $areas,
                $productMatchOrder,
                $productMediaByProductId,
                $productVisibilitiesByProductId,
                $onItem,
                $completeBatch,
                $ensureActive,
            ): void {
                foreach ($products as $sourceProduct) {
                    $ensureActive();
                    $sourceProduct = $this->withProductVisibilities(
                        $sourceProduct,
                        $productVisibilitiesByProductId,
                    );
                    $this->importProduct(
                        $sourceProduct,
                        $connection,
                        $tenant,
                        $locale,
                        $currencyCodes,
                        $areas,
                        $productMatchOrder,
                        $productMediaByProductId,
                        $onItem,
                    );
                    $completeBatch();
                }
            },
            'parents',
        );

        if ($areas['variants']) {
            $this->shopwareClient->forEachProductPage(
                $baseUrl,
                $secrets,
                function (int $total, array $products) use (
                    &$connection,
                    &$tenant,
                    &$locale,
                    $currencyCodes,
                    $areas,
                    $productMatchOrder,
                    $productMediaByProductId,
                    $productVisibilitiesByProductId,
                    $onItem,
                    $completeBatch,
                    $ensureActive,
                ): void {
                    foreach ($products as $sourceVariant) {
                        $ensureActive();
                        $attributes = $this->attributes($sourceVariant);
                        $parentExternalId = $this->parentExternalId($sourceVariant, $attributes);
                        $sourceVariant = $this->withProductVisibilities(
                            $sourceVariant,
                            $productVisibilitiesByProductId,
                            $parentExternalId,
                        );
                        $attributes = $this->attributes($sourceVariant);
                        $productReference = $this->productReference($sourceVariant, $attributes);
                        $parent = $parentExternalId === null
                            ? null
                            : $this->mappedEntity(
                                $connection,
                                'product',
                                $parentExternalId,
                                Product::class,
                                $tenant,
                            );
                        if (!$parent instanceof Product) {
                            $onItem(
                                false,
                                'The source parent product could not be resolved.',
                                $productReference,
                            );
                            $completeBatch();

                            continue;
                        }

                        $this->importProduct(
                            $sourceVariant,
                            $connection,
                            $tenant,
                            $locale,
                            $currencyCodes,
                            $areas,
                            $productMatchOrder,
                            $productMediaByProductId,
                            $onItem,
                            $parent,
                        );
                        $completeBatch();
                    }
                },
                'variants',
            );
        }

        if ($areas['translations']) {
            $ensureActive();
            $onStage('translations');
            $this->importTranslations(
                $run,
                $baseUrl,
                $secrets,
                $areas['customFields'],
                $ensureActive,
            );
        }

        $ensureActive();
        $onStage('seoUrls');
        $this->seoUrlImporter->import(
            $tenant,
            $connection,
            $baseUrl,
            $secrets,
            $ensureActive,
        );

        $ensureActive();
        $this->entityManager->clear();
    }

    /**
     * @return array{IntegrationConnection, Tenant, Locale}
     */
    private function reloadContext(
        Uuid $connectionId,
        Uuid $tenantId,
        Uuid $localeId,
    ): array {
        $connection = $this->entityManager->find(IntegrationConnection::class, $connectionId);
        $tenant = $this->entityManager->find(Tenant::class, $tenantId);
        $locale = $this->entityManager->find(Locale::class, $localeId);
        if (
            !$connection instanceof IntegrationConnection
            || !$tenant instanceof Tenant
            || !$locale instanceof Locale
        ) {
            throw new \RuntimeException('The import context could not be reloaded.');
        }

        return [$connection, $tenant, $locale];
    }

    /**
     * Reads each enabled Shopware language through its Admin API language
     * context, so translated content is not limited to the source default.
     *
     * @param array<string, string> $secrets
     * @param callable(): void $ensureActive
     */
    private function importTranslations(
        IntegrationImportRun $run,
        string $baseUrl,
        array $secrets,
        bool $importCustomFields,
        callable $ensureActive,
    ): void {
        $translationLocales = $this->translationLocales(
            $run->getTenant(),
            $baseUrl,
            $secrets,
        );
        $connectionId = $run->getConnection()->getId();
        $tenantId = $run->getTenant()->getId();

        foreach ($translationLocales as $shopwareLanguageId => $localeId) {
            $ensureActive();
            [$connection, $tenant, $locale] = $this->reloadContext(
                $connectionId,
                $tenantId,
                $localeId,
            );
            $processed = 0;
            $onPage = function (int $total, array $products) use (
                &$connection,
                &$tenant,
                &$locale,
                &$processed,
                $connectionId,
                $tenantId,
                $localeId,
                $importCustomFields,
                $ensureActive,
            ): void {
                foreach ($products as $sourceProduct) {
                    $ensureActive();
                    $attributes = $this->attributes($sourceProduct);
                    $externalId = $this->string(
                        $sourceProduct['id'] ?? $attributes['id'] ?? null,
                    );
                    $name = $this->name($attributes, null);
                    if ($externalId === null || $name === null) {
                        continue;
                    }

                    $product = $this->mappedEntity(
                        $connection,
                        'product',
                        $externalId,
                        Product::class,
                        $tenant,
                    );
                    if (!$product instanceof Product) {
                        continue;
                    }

                    $this->updateTranslation(
                        $product,
                        $locale,
                        $attributes,
                        $name,
                        $importCustomFields,
                    );
                    ++$processed;
                    if ($processed % self::BATCH_SIZE !== 0) {
                        continue;
                    }

                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    [$connection, $tenant, $locale] = $this->reloadContext(
                        $connectionId,
                        $tenantId,
                        $localeId,
                    );
                }
            };

            $this->shopwareClient->forEachProductPage(
                $baseUrl,
                $secrets,
                $onPage,
                'parents',
                languageId: $shopwareLanguageId,
            );
            $this->shopwareClient->forEachProductPage(
                $baseUrl,
                $secrets,
                $onPage,
                'variants',
                languageId: $shopwareLanguageId,
            );

            $this->entityManager->flush();
            $this->entityManager->clear();
        }
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

    /**
     * @param array<string, mixed> $source
     * @param array<string, string> $currencyCodes
     * @param array<string, bool> $areas
     * @param list<string> $productMatchOrder
     * @param array<string, list<array{id: string, mediaId: string, position: int}>> $productMediaByProductId
     * @param callable(bool, ?string, ?string): void $onItem
     */
    private function importProduct(
        array $source,
        IntegrationConnection $connection,
        Tenant $tenant,
        Locale $locale,
        array $currencyCodes,
        array $areas,
        array $productMatchOrder,
        array $productMediaByProductId,
        callable $onItem,
        ?Product $parent = null,
    ): void {
        $attributes = $this->attributes($source);
        $productReference = $this->productReference($source, $attributes);

        try {
            [, $created] = $this->upsertProduct(
                $source,
                $connection,
                $tenant,
                $locale,
                $currencyCodes,
                $areas,
                $productMatchOrder,
                $productMediaByProductId,
                $parent,
            );
            $this->entityManager->flush();
            $onItem($created, null, $productReference);
        } catch (ImportCancelledException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $onItem(false, $exception->getMessage(), $productReference);
        }
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, string> $currencyCodes
     * @param array<string, bool> $areas
     * @param list<string> $productMatchOrder
     * @param array<string, list<array{id: string, mediaId: string, position: int}>> $productMediaByProductId
     *
     * @return array{Product, bool}
     */
    private function upsertProduct(
        array $source,
        IntegrationConnection $connection,
        Tenant $tenant,
        Locale $locale,
        array $currencyCodes,
        array $areas,
        array $productMatchOrder,
        array $productMediaByProductId,
        ?Product $parent = null,
    ): array {
        $attributes = $this->attributes($source);
        $externalId = $this->string($source['id'] ?? $attributes['id'] ?? null);
        $sku = $this->nullable($attributes['productNumber'] ?? null);
        $name = $this->name($attributes, $sku);
        if ($externalId === null || $name === null) {
            throw new \RuntimeException('The Shopware product is missing an identifier or name.');
        }

        $mapping = $this->entityManager->getRepository(IntegrationEntityMapping::class)
            ->findOneBy([
                'connection' => $connection,
                'entityType' => 'product',
                'externalId' => $externalId,
            ]);
        $product = $mapping instanceof IntegrationEntityMapping
            ? $this->entityManager->getRepository(Product::class)->findOneBy([
                'id' => $mapping->getLocalId(),
                'tenant' => $tenant,
            ])
            : null;

        $ean = $this->nullable($attributes['ean'] ?? null);
        foreach ($productMatchOrder as $matchKey) {
            if ($product instanceof Product) {
                break;
            }
            if ($matchKey === 'sku' && $sku !== null) {
                $product = $this->entityManager->getRepository(Product::class)
                    ->findOneBy([
                        'tenant' => $tenant,
                        'sku' => $sku,
                    ]);
            }
            if ($matchKey === 'ean' && $ean !== null) {
                $product = $this->entityManager->getRepository(Product::class)->findOneBy([
                    'tenant' => $tenant,
                    'ean' => $ean,
                ]);
            }
        }

        $created = !($product instanceof Product);
        if ($created) {
            $product = new Product($tenant);
            $this->entityManager->persist($product);
        }

        if (!$mapping instanceof IntegrationEntityMapping) {
            $mapping = new IntegrationEntityMapping(
                $tenant,
                $connection,
                'product',
                $externalId,
                $product->getId(),
            );
            $this->entityManager->persist($mapping);
        } elseif ($mapping->getLocalId()->toRfc4122() !== $product->getId()->toRfc4122()) {
            $mapping->remap($product->getId());
        }

        if ($parent instanceof Product) {
            $product->makeChildOf(
                $parent,
                $sku ?? $parent->getSku().'-'.substr($externalId, 0, 8),
                $ean,
                $this->variantOptions($connection, $tenant, $attributes),
            );
        } else {
            $product->updateIdentity($sku, $ean);
        }

        $tax = $areas['taxes']
            ? $this->tax($tenant, $connection, $attributes)
            : $product->getTax();
        $deliveryTime = $areas['deliveryTimes']
            ? $this->deliveryTime($tenant, $connection, $attributes)
            : $product->getDeliveryTimeReference();
        $unit = $areas['units']
            ? $this->unit($tenant, $connection, $attributes)
            : $product->getUnit();

        $product->updateCommerce(
            $this->productType($attributes['productType'] ?? null),
            $this->nullable($attributes['manufacturerNumber'] ?? null),
            null,
            $this->deliveryTimeName($attributes, $deliveryTime, $locale),
            $this->date($attributes['releaseDate'] ?? null),
            false,
            $this->visibility($attributes['visibilities'] ?? []),
        );
        $product->updateStatus(
            (bool) ($attributes['active'] ?? false) ? 'active' : 'draft',
        );
        $product->updatePackUnits(
            $this->nullable($attributes['packUnit'] ?? null),
            $this->nullable($attributes['packUnitPlural'] ?? null),
        );
        $product->updateFulfilment(
            $this->decimal($attributes['minPurchase'] ?? null, '1.0000'),
            $this->decimal($attributes['purchaseSteps'] ?? null, '1.0000'),
            $this->decimalOrNull($attributes['maxPurchase'] ?? null),
            $this->integer($attributes['restockTime'] ?? null),
            (bool) ($attributes['isCloseout'] ?? false),
            (bool) ($attributes['shippingFree'] ?? false),
            $this->keywords($attributes),
            $this->millimeters($attributes['weight'] ?? null, 1000),
            $this->millimeters($attributes['length'] ?? null),
            $this->millimeters($attributes['width'] ?? null),
            $this->millimeters($attributes['height'] ?? null),
        );
        if ($areas['manufacturers']) {
            $product->updateManufacturer(
                $this->manufacturer($tenant, $connection, $locale, $attributes),
            );
        }
        if ($areas['taxes'] || $areas['deliveryTimes'] || $areas['units']) {
            $product->updateReferences(
                $tax,
                $unit,
                $this->decimalOrNull($attributes['purchaseUnit'] ?? null),
                $this->decimalOrNull($attributes['referenceUnit'] ?? null),
                $deliveryTime,
            );
        }

        if ($areas['prices']) {
            $price = $this->prices(
                $tenant,
                is_array($attributes['price'] ?? null)
                    ? $attributes['price']
                    : [],
                $currencyCodes,
            );
            if ($price !== []) {
                $product->updatePrices($price, [], []);
            }
            $this->syncAdvancedPrices(
                $product,
                $tenant,
                $attributes,
                $currencyCodes,
                $tax?->getRate() ?? '0.00',
            );
        }

        if ($created || $areas['translations']) {
            $this->updateTranslation(
                $product,
                $locale,
                $attributes,
                $name,
                $areas['customFields'],
            );
        }

        if ($areas['categories']) {
            $this->assignCategories($product, $connection, $tenant, $source, $attributes);
        }
        if ($areas['properties']) {
            $this->assignProperties($product, $connection, $tenant, $source, $attributes);
        }
        if ($areas['tags']) {
            $this->assignTags($product, $connection, $tenant, $source, $attributes);
        }
        if ($areas['channelPublications']) {
            $this->syncChannelPublications($product, $connection, $tenant, $attributes);
        }
        if ($areas['stock']) {
            $this->syncStock($tenant, $product, $attributes);
        }
        if ($areas['media']) {
            $this->assignMedia(
                $product,
                $connection,
                $tenant,
                $externalId,
                $attributes,
                $productMediaByProductId,
            );
        }

        return [$product, $created];
    }

    /** @param array<string, mixed> $source */
    private function attributes(array $source): array
    {
        return is_array($source['attributes'] ?? null)
            ? $source['attributes']
            : $source;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, list<array{productId: string, salesChannelId: string, visibility: int}>> $productVisibilitiesByProductId
     *
     * @return array<string, mixed>
     */
    private function withProductVisibilities(
        array $source,
        array $productVisibilitiesByProductId,
        ?string $inheritedFromExternalId = null,
    ): array {
        $attributes = $this->attributes($source);
        $externalId = $this->string($source['id'] ?? $attributes['id'] ?? null);
        if ($externalId === null) {
            return $source;
        }

        $visibilities = $productVisibilitiesByProductId[$externalId] ?? null;
        if ($visibilities === null && $inheritedFromExternalId !== null) {
            $visibilities = array_map(
                static fn (array $visibility): array => [
                    ...$visibility,
                    'productId' => $externalId,
                ],
                $productVisibilitiesByProductId[$inheritedFromExternalId] ?? [],
            );
        }
        $visibilities ??= [];
        if (is_array($source['attributes'] ?? null)) {
            $source['attributes']['visibilities'] = $visibilities;
        } else {
            $source['visibilities'] = $visibilities;
        }

        return $source;
    }

    /** @param array<string, mixed> $source */
    private function parentExternalId(array $source, array $attributes): ?string
    {
        return $this->nullable($attributes['parentId'] ?? $source['parentId'] ?? null);
    }

    /** @param array<string, mixed> $source */
    private function productReference(array $source, array $attributes): ?string
    {
        return $this->nullable(
            $attributes['productNumber']
            ?? $attributes['id']
            ?? $source['id']
            ?? null,
        );
    }

    /** @param array<string, mixed> $attributes */
    private function name(array $attributes, ?string $sku): ?string
    {
        $translated = is_array($attributes['translated'] ?? null)
            ? $attributes['translated']
            : [];

        return $this->nullable($translated['name'] ?? $attributes['name'] ?? $sku);
    }

    /** @param array<string, mixed> $attributes */
    private function updateTranslation(
        Product $product,
        Locale $locale,
        array $attributes,
        string $name,
        bool $importCustomFields,
    ): void {
        $translated = is_array($attributes['translated'] ?? null)
            ? $attributes['translated']
            : [];
        $translation = $this->entityManager->getRepository(ProductTranslation::class)
            ->findOneBy([
                'product' => $product,
                'locale' => $locale,
            ]);
        $translation ??= new ProductTranslation($product, $locale, $name);
        $description = $this->nullable(
            $translated['description'] ?? $attributes['description'] ?? null,
        );

        $translation->update(
            $name,
            $this->shortDescription($description),
            $description,
            $this->nullable($translated['metaTitle'] ?? $attributes['metaTitle'] ?? null),
            $this->nullable($translated['metaDescription'] ?? $attributes['metaDescription'] ?? null),
            $this->nullable($translated['metaKeywords'] ?? $attributes['metaKeywords'] ?? null),
            $importCustomFields && is_array($attributes['customFields'] ?? null)
                ? $attributes['customFields']
                : ($translation?->getCustomFields() ?? []),
        );
        $this->entityManager->persist($translation);
        $this->seoUrls->syncCanonical(
            $product->getTenant(),
            SeoUrlService::ENTITY_PRODUCT,
            $product->getId(),
            $locale,
            $name,
            source: 'import',
        );
    }

    private function shortDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $plainText = preg_replace(
            '#<(script|style)\\b[^>]*>.*?</\\1>#is',
            ' ',
            $description,
        );
        $plainText = preg_replace(
            '#</?(?:p|div|br|li|h[1-6]|tr|td|blockquote)\\b[^>]*>#i',
            ' ',
            $plainText ?? $description,
        );
        $plainText = html_entity_decode(
            strip_tags($plainText ?? $description),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $plainText = trim(preg_replace('/\\s+/u', ' ', $plainText) ?? '');
        if ($plainText === '') {
            return null;
        }

        preg_match_all('/[^.!?]+(?:[.!?]+(?=\\s|$)|$)/u', $plainText, $matches);
        $sentences = array_values(array_filter(array_map(
            static fn (string $sentence): string => trim($sentence),
            $matches[0] ?? [],
        ), static fn (string $sentence): bool => $sentence !== ''));

        return implode(' ', array_slice($sentences, 0, 2)) ?: $plainText;
    }

    /** @param array<string, mixed> $attributes */
    private function manufacturer(
        Tenant $tenant,
        IntegrationConnection $connection,
        Locale $locale,
        array $attributes,
    ): ?Manufacturer {
        $externalId = $this->string($attributes['manufacturerId'] ?? null);
        if ($externalId !== null) {
            $manufacturer = $this->mappedEntity(
                $connection,
                'manufacturer',
                $externalId,
                Manufacturer::class,
                $tenant,
            );
            if ($manufacturer instanceof Manufacturer) {
                return $manufacturer;
            }
        }

        $manufacturerData = $attributes['manufacturer'] ?? null;
        if (!is_array($manufacturerData)) {
            return null;
        }

        $manufacturerAttributes = $this->attributes($manufacturerData);
        $externalId = $this->string(
            $manufacturerData['id'] ?? $manufacturerAttributes['id'] ?? null,
        );
        if ($externalId !== null) {
            $manufacturer = $this->mappedEntity(
                $connection,
                'manufacturer',
                $externalId,
                Manufacturer::class,
                $tenant,
            );
            if ($manufacturer instanceof Manufacturer) {
                return $manufacturer;
            }
        }
        $translated = is_array($manufacturerAttributes['translated'] ?? null)
            ? $manufacturerAttributes['translated']
            : [];
        $name = $this->nullable(
            $translated['name'] ?? $manufacturerAttributes['name'] ?? null,
        );
        if ($name === null) {
            return null;
        }

        $translation = $this->entityManager->getRepository(ManufacturerTranslation::class)
            ->findOneBy([
                'locale' => $locale,
                'name' => $name,
            ]);
        if ($translation instanceof ManufacturerTranslation) {
            return $translation->getManufacturer();
        }

        $manufacturer = new Manufacturer($tenant);
        $manufacturer->update(
            $this->nullable($manufacturerAttributes['link'] ?? null),
            null,
        );
        $translation = new ManufacturerTranslation($manufacturer, $locale, $name);
        $translation->update(
            $name,
            $this->nullable($manufacturerAttributes['description'] ?? null),
            null,
            null,
            null,
            is_array($manufacturerAttributes['customFields'] ?? null)
                ? $manufacturerAttributes['customFields']
                : [],
        );
        $this->entityManager->persist($manufacturer);
        $this->entityManager->persist($translation);
        $this->seoUrls->syncCanonical(
            $tenant,
            SeoUrlService::ENTITY_MANUFACTURER,
            $manufacturer->getId(),
            $locale,
            $name,
            source: 'import',
        );

        return $manufacturer;
    }

    /** @param array<string, mixed> $attributes */
    private function tax(
        Tenant $tenant,
        IntegrationConnection $connection,
        array $attributes,
    ): ?Tax
    {
        $externalId = $this->string($attributes['taxId'] ?? null);
        if ($externalId !== null) {
            $tax = $this->mappedEntity(
                $connection,
                'tax',
                $externalId,
                Tax::class,
                $tenant,
            );
            if ($tax instanceof Tax) {
                return $tax;
            }
        }

        $taxData = $attributes['tax'] ?? null;
        if (!is_array($taxData)) {
            return null;
        }

        $taxAttributes = $this->attributes($taxData);
        $externalId = $this->string($taxData['id'] ?? $taxAttributes['id'] ?? null);
        if ($externalId !== null) {
            $tax = $this->mappedEntity(
                $connection,
                'tax',
                $externalId,
                Tax::class,
                $tenant,
            );
            if ($tax instanceof Tax) {
                return $tax;
            }
        }
        $rate = $this->decimalOrNull($taxAttributes['taxRate'] ?? $taxAttributes['rate'] ?? null);
        if ($rate === null) {
            return null;
        }

        $name = $this->nullable($taxAttributes['name'] ?? null)
            ?? sprintf('VAT %s%%', rtrim(rtrim($rate, '0'), '.'));
        $tax = $this->entityManager->getRepository(Tax::class)->findOneBy([
            'tenant' => $tenant,
            'name' => $name,
        ]);
        if ($tax instanceof Tax) {
            return $tax;
        }

        $tax = new Tax($tenant, $name, $rate);
        $this->entityManager->persist($tax);

        return $tax;
    }

    /**
     * @param array<mixed> $sourcePrices
     * @param array<string, string> $currencyCodes
     *
     * @return array<string, array<string, mixed>>
     */
    private function prices(
        Tenant $tenant,
        array $sourcePrices,
        array $currencyCodes,
    ): array {
        $prices = [];
        foreach ($sourcePrices as $sourcePrice) {
            if (!is_array($sourcePrice)) {
                continue;
            }

            $currencyId = $this->string($sourcePrice['currencyId'] ?? null);
            $currencyCode = $currencyId === null
                ? null
                : ($currencyCodes[$currencyId] ?? null);
            if ($currencyCode === null) {
                continue;
            }

            $currency = $this->currency($tenant, $currencyCode);
            $gross = $this->number($sourcePrice['gross'] ?? null);
            $net = $this->number($sourcePrice['net'] ?? null);
            if ($gross === null && $net === null) {
                continue;
            }

            $price = [
                'currencyId' => $currency->getId()->toRfc4122(),
                'currencyCode' => $currency->getCode(),
                'gross' => $gross ?? 0.0,
                'net' => $net ?? 0.0,
                'linked' => (bool) ($sourcePrice['linked'] ?? true),
            ];
            if (is_array($sourcePrice['listPrice'] ?? null)) {
                $price['listPrice'] = $this->nestedPrice(
                    $sourcePrice['listPrice'],
                    $currency,
                );
            }
            if (is_array($sourcePrice['regulationPrice'] ?? null)) {
                $price['regulationPrice'] = $this->nestedPrice(
                    $sourcePrice['regulationPrice'],
                    $currency,
                );
            }
            $prices[$currency->getId()->toRfc4122()] = $price;
        }

        return $prices;
    }

    /**
     * Synchronizes Shopware's rule-based and quantity-tier prices into the
     * existing advanced-price rows, keyed by the source rule and tier start.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, string> $currencyCodes
     */
    private function syncAdvancedPrices(
        Product $product,
        Tenant $tenant,
        array $attributes,
        array $currencyCodes,
        string $taxRate,
    ): void {
        if (!array_key_exists('prices', $attributes)) {
            return;
        }

        $expected = [];
        foreach ($this->relatedItems($attributes['prices']) as $sourcePrice) {
            $sourceAttributes = $this->attributes($sourcePrice);
            $sourceEntries = $sourceAttributes['price'] ?? null;
            if (!is_array($sourceEntries)) {
                continue;
            }

            $quantityStart = $this->decimal(
                $sourceAttributes['quantityStart'] ?? null,
                '1.0000',
            );
            $quantityEnd = $this->decimalOrNull(
                $sourceAttributes['quantityEnd'] ?? null,
            );
            if ($quantityEnd !== null && (float) $quantityEnd <= (float) $quantityStart) {
                $quantityEnd = null;
            }
            $ruleId = $this->string($sourceAttributes['ruleId'] ?? null);
            $pricingContext = $ruleId === null
                ? 'shopware-default'
                : 'shopware-rule:'.$ruleId;
            $prices = $this->prices($tenant, $sourceEntries, $currencyCodes);
            foreach ($prices as $currencyId => $price) {
                $expected[$currencyId.'|'.$pricingContext.'|'.$quantityStart] = [
                    'currencyId' => $currencyId,
                    'price' => $price,
                    'quantityStart' => $quantityStart,
                    'quantityEnd' => $quantityEnd,
                    'pricingContext' => $pricingContext,
                    'validFrom' => $this->date($sourceAttributes['validFrom'] ?? null),
                    'validUntil' => $this->date($sourceAttributes['validUntil'] ?? null),
                ];
            }
        }

        $existingImported = [];
        foreach ($this->entityManager->getRepository(ProductPrice::class)
            ->findBy(['product' => $product]) as $price) {
            if ($price->getSource() !== 'import') {
                continue;
            }
            $existingImported[implode('|', [
                $price->getCurrency()->getId()->toRfc4122(),
                $price->getPricingContext(),
                $price->getQuantityStart(),
            ])] = $price;
        }

        foreach ($existingImported as $key => $price) {
            if (!isset($expected[$key])) {
                $this->entityManager->remove($price);
            }
        }
        foreach ($expected as $key => $source) {
            $currency = $this->entityManager->find(Currency::class, Uuid::fromString($source['currencyId']));
            if (!$currency instanceof Currency) {
                continue;
            }

            $price = $existingImported[$key] ?? new ProductPrice(
                $tenant,
                $product,
                $currency,
                'default',
                [$source['currencyId'] => $source['price']],
                $taxRate,
            );
            $price->updatePrice([$source['currencyId'] => $source['price']], $taxRate);
            $price->updateAdvanced(
                $source['quantityStart'],
                $source['quantityEnd'],
                $source['pricingContext'],
                $source['validFrom'],
                $source['validUntil'],
                'import',
            );
            if (!isset($existingImported[$key])) {
                $this->entityManager->persist($price);
            }
        }
    }

    /**
     * @param array<string, mixed> $sourcePrice
     *
     * @return array<string, mixed>
     */
    private function nestedPrice(array $sourcePrice, Currency $currency): array
    {
        return [
            'currencyId' => $currency->getId()->toRfc4122(),
            'currencyCode' => $currency->getCode(),
            'gross' => $this->number($sourcePrice['gross'] ?? null) ?? 0.0,
            'net' => $this->number($sourcePrice['net'] ?? null) ?? 0.0,
            'linked' => (bool) ($sourcePrice['linked'] ?? true),
        ];
    }

    private function currency(Tenant $tenant, string $code): Currency
    {
        $currency = $this->entityManager->getRepository(Currency::class)
            ->findOneBy(['code' => $code]);
        if (!$currency instanceof Currency) {
            $currency = new Currency($code, $this->currencySymbol($code), 2);
            $this->entityManager->persist($currency);
        }

        $tenantCurrency = $this->entityManager->getRepository(TenantCurrency::class)
            ->findOneBy([
                'tenant' => $tenant,
                'currency' => $currency,
            ]);
        if (!$tenantCurrency instanceof TenantCurrency) {
            $this->entityManager->persist(new TenantCurrency($tenant, $currency));
        }

        return $currency;
    }

    /** @param array<string, mixed> $attributes */
    private function deliveryTimeName(
        array $attributes,
        ?DeliveryTime $deliveryTime,
        Locale $locale,
    ): ?string
    {
        $deliveryTime = $attributes['deliveryTime'] ?? null;
        if (is_array($deliveryTime)) {
            $deliveryTimeAttributes = $this->attributes($deliveryTime);
            $translated = is_array($deliveryTimeAttributes['translated'] ?? null)
                ? $deliveryTimeAttributes['translated']
                : [];
            $name = $this->nullable(
                $translated['name'] ?? $deliveryTimeAttributes['name'] ?? null,
            );
            if ($name !== null) {
                return $name;
            }
        }

        $label = $deliveryTime?->getLabels()[$locale->getCode()] ?? null;

        return is_string($label) && $label !== '' ? $label : null;
    }

    /** @param array<string, mixed> $attributes */
    private function deliveryTime(
        Tenant $tenant,
        IntegrationConnection $connection,
        array $attributes,
    ): ?DeliveryTime {
        $externalId = $this->string($attributes['deliveryTimeId'] ?? null);
        if ($externalId !== null) {
            $entity = $this->mappedEntity(
                $connection,
                'delivery_time',
                $externalId,
                DeliveryTime::class,
                $tenant,
            );
            if ($entity instanceof DeliveryTime) {
                return $entity;
            }
        }

        $deliveryTime = $attributes['deliveryTime'] ?? null;
        if (!is_array($deliveryTime)) {
            return null;
        }
        $deliveryTimeAttributes = $this->attributes($deliveryTime);
        $externalId = $this->string(
            $deliveryTime['id'] ?? $deliveryTimeAttributes['id'] ?? null,
        );
        if ($externalId === null) {
            return null;
        }

        $entity = $this->mappedEntity(
            $connection,
            'delivery_time',
            $externalId,
            DeliveryTime::class,
            $tenant,
        );

        return $entity instanceof DeliveryTime ? $entity : null;
    }

    /** @param array<string, mixed> $attributes */
    private function unit(
        Tenant $tenant,
        IntegrationConnection $connection,
        array $attributes,
    ): ?Unit {
        $externalId = $this->string($attributes['unitId'] ?? null);
        if ($externalId === null && is_array($attributes['unit'] ?? null)) {
            $unitAttributes = $this->attributes($attributes['unit']);
            $externalId = $this->string(
                $attributes['unit']['id'] ?? $unitAttributes['id'] ?? null,
            );
        }
        if ($externalId === null) {
            return null;
        }

        $unit = $this->mappedEntity(
            $connection,
            'unit',
            $externalId,
            Unit::class,
            $tenant,
        );

        return $unit instanceof Unit ? $unit : null;
    }

    private function assignCategories(
        Product $product,
        IntegrationConnection $connection,
        Tenant $tenant,
        array $source,
        array $attributes,
    ): void {
        $sourceCategoryIds = $this->sourceIdentifiers(
            $source,
            $attributes,
            'categoryIds',
            'categories',
        );
        if ($sourceCategoryIds === null) {
            return;
        }

        $categories = [];
        foreach ($sourceCategoryIds as $externalId) {
            $category = $this->mappedEntity(
                $connection,
                'category',
                $externalId,
                Category::class,
                $tenant,
            );
            if ($category instanceof Category) {
                $categories[$category->getId()->toRfc4122()] = $category;
            }
        }

        $existingAssignments = $this->entityManager
            ->getRepository(CategoryProduct::class)
            ->findBy(['product' => $product]);
        $existingByCategoryId = [];
        foreach ($existingAssignments as $assignment) {
            $existingByCategoryId[$assignment->getCategory()->getId()->toRfc4122()] = $assignment;
        }
        foreach ($existingByCategoryId as $categoryId => $assignment) {
            if (!isset($categories[$categoryId])) {
                $this->entityManager->remove($assignment);
            }
        }
        foreach (array_values($categories) as $position => $category) {
            if (!isset($existingByCategoryId[$category->getId()->toRfc4122()])) {
                $this->entityManager->persist(new CategoryProduct(
                    $category,
                    $product,
                    $position,
                ));
            }
        }
    }

    private function assignProperties(
        Product $product,
        IntegrationConnection $connection,
        Tenant $tenant,
        array $source,
        array $attributes,
    ): void {
        $sourcePropertyIds = $this->sourceIdentifiers(
            $source,
            $attributes,
            'propertyIds',
            'properties',
        );
        if ($sourcePropertyIds === null) {
            return;
        }

        $properties = [];
        foreach ($sourcePropertyIds as $externalId) {
            $property = $this->mappedEntity(
                $connection,
                'property',
                $externalId,
                Property::class,
                $tenant,
            );
            if ($property instanceof Property) {
                $properties[$property->getId()->toRfc4122()] = $property;
            }
        }

        $existingAssignments = $this->entityManager
            ->getRepository(ProductPropertyAssignment::class)
            ->findBy(['product' => $product]);
        $existingByPropertyId = [];
        foreach ($existingAssignments as $assignment) {
            $existingByPropertyId[$assignment->getProperty()->getId()->toRfc4122()] = $assignment;
        }
        foreach ($existingByPropertyId as $propertyId => $assignment) {
            if (!isset($properties[$propertyId])) {
                $this->entityManager->remove($assignment);
            }
        }
        foreach ($properties as $property) {
            if (!isset($existingByPropertyId[$property->getId()->toRfc4122()])) {
                $this->entityManager->persist(new ProductPropertyAssignment(
                    $tenant,
                    $product,
                    $property,
                    'shopware',
                ));
            }
        }
    }

    private function assignTags(
        Product $product,
        IntegrationConnection $connection,
        Tenant $tenant,
        array $source,
        array $attributes,
    ): void {
        $sourceTagIds = $this->sourceIdentifiers(
            $source,
            $attributes,
            'tagIds',
            'tags',
        );
        if ($sourceTagIds === null) {
            return;
        }

        $tags = [];
        foreach ($sourceTagIds as $externalId) {
            $tag = $this->mappedEntity(
                $connection,
                'tag',
                $externalId,
                Tag::class,
                $tenant,
            );
            if ($tag instanceof Tag) {
                $tags[$tag->getId()->toRfc4122()] = $tag;
            }
        }

        $existingAssignments = $this->entityManager
            ->getRepository(ProductTag::class)
            ->findBy(['product' => $product]);
        $existingByTagId = [];
        foreach ($existingAssignments as $assignment) {
            $existingByTagId[$assignment->getTag()->getId()->toRfc4122()] = $assignment;
        }
        foreach ($existingByTagId as $tagId => $assignment) {
            if (!isset($tags[$tagId])) {
                $this->entityManager->remove($assignment);
            }
        }
        foreach ($tags as $tagId => $tag) {
            if (!isset($existingByTagId[$tagId])) {
                $this->entityManager->persist(new ProductTag($product, $tag));
            }
        }
    }

    /** @param array<string, mixed> $attributes */
    private function syncChannelPublications(
        Product $product,
        IntegrationConnection $connection,
        Tenant $tenant,
        array $attributes,
    ): void {
        if (!array_key_exists('visibilities', $attributes)) {
            return;
        }

        $publications = [];
        foreach ($this->visibility($attributes['visibilities']) as $source) {
            $salesChannelExternalId = $this->string($source['salesChannelId'] ?? null);
            $visibility = $source['visibility'] ?? null;
            if (
                $salesChannelExternalId === null
                || !is_numeric($visibility)
                || !in_array((int) $visibility, [10, 20, 30], true)
            ) {
                continue;
            }

            $salesChannel = $this->entityManager
                ->getRepository(IntegrationSalesChannel::class)
                ->findOneBy([
                    'tenant' => $tenant,
                    'connection' => $connection,
                    'externalId' => $salesChannelExternalId,
                ]);
            if (!$salesChannel instanceof IntegrationSalesChannel) {
                continue;
            }

            $publications[$salesChannel->getId()->toRfc4122()] = [
                'salesChannel' => $salesChannel,
                'visibility' => (int) $visibility,
                'externalProductId' => $this->string($source['productId'] ?? null),
            ];
        }

        $existingBySalesChannelId = [];
        foreach ($this->entityManager->getRepository(ProductChannelPublication::class)
            ->findBy(['product' => $product]) as $publication) {
            if ($publication->getSalesChannel()->getConnection()->getId()->equals($connection->getId())) {
                $existingBySalesChannelId[
                    $publication->getSalesChannel()->getId()->toRfc4122()
                ] = $publication;
            }
        }
        foreach ($existingBySalesChannelId as $salesChannelId => $publication) {
            if (!isset($publications[$salesChannelId])) {
                $this->entityManager->remove($publication);
            }
        }
        foreach ($publications as $salesChannelId => $source) {
            $publication = $existingBySalesChannelId[$salesChannelId] ?? null;
            if ($publication instanceof ProductChannelPublication) {
                $publication->update($source['visibility'], $source['externalProductId']);

                continue;
            }

            $this->entityManager->persist(new ProductChannelPublication(
                $tenant,
                $product,
                $source['salesChannel'],
                $source['visibility'],
                $source['externalProductId'],
            ));
        }
    }

    /** @param array<string, mixed> $attributes @return array<string, string> */
    private function variantOptions(
        IntegrationConnection $connection,
        Tenant $tenant,
        array $attributes,
    ): array {
        $values = [];
        foreach ($this->identifiers($attributes['optionIds'] ?? null) as $externalId) {
            $property = $this->mappedEntity(
                $connection,
                'property',
                $externalId,
                Property::class,
                $tenant,
            );
            if (!$property instanceof Property) {
                continue;
            }
            $values[$property->getPropertyGroup()->getId()->toRfc4122()] = $property
                ->getId()
                ->toRfc4122();
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, list<array{id: string, mediaId: string, position: int}>> $productMediaByProductId
     */
    private function assignMedia(
        Product $product,
        IntegrationConnection $connection,
        Tenant $tenant,
        string $externalProductId,
        array $attributes,
        array $productMediaByProductId,
    ): void {
        $coverExternalId = $this->string($attributes['coverId'] ?? null);
        $sourceItems = [];
        foreach ($productMediaByProductId[$externalProductId] ?? [] as $sourceProductMedia) {
            $media = $this->mappedEntity(
                $connection,
                'media',
                $sourceProductMedia['mediaId'],
                Media::class,
                $tenant,
            );
            if (
                !$media instanceof Media
                || !str_starts_with($media->getMimeType() ?? '', 'image/')
            ) {
                continue;
            }

            $sourceItems[] = [
                'externalId' => $sourceProductMedia['id'],
                'media' => $media,
                'position' => $sourceProductMedia['position'],
                'isCover' => $coverExternalId === $sourceProductMedia['id'],
            ];
        }
        if ($sourceItems === []) {
            return;
        }

        usort($sourceItems, static function (array $left, array $right): int {
            if ($left['isCover'] !== $right['isCover']) {
                return $left['isCover'] ? -1 : 1;
            }

            return [
                $left['position'],
                $left['externalId'],
            ] <=> [
                $right['position'],
                $right['externalId'],
            ];
        });

        $existingItems = $this->entityManager
            ->getRepository(ProductMedia::class)
            ->findBy(['product' => $product], ['sortOrder' => 'ASC']);
        $managedItemIds = [];
        $itemsByExternalId = [];
        foreach ($sourceItems as $sourceItem) {
            $mapping = $this->entityManager
                ->getRepository(IntegrationEntityMapping::class)
                ->findOneBy([
                    'connection' => $connection,
                    'entityType' => 'product_media',
                    'externalId' => $sourceItem['externalId'],
                ]);
            if (!$mapping instanceof IntegrationEntityMapping) {
                continue;
            }

            $item = $this->entityManager->getRepository(ProductMedia::class)->findOneBy([
                'id' => $mapping->getLocalId(),
                'product' => $product,
            ]);
            if (!$item instanceof ProductMedia) {
                continue;
            }

            $itemsByExternalId[$sourceItem['externalId']] = $item;
            $managedItemIds[$item->getId()->toRfc4122()] = true;
        }

        foreach ($existingItems as $position => $item) {
            if (isset($managedItemIds[$item->getId()->toRfc4122()])) {
                $item->setSortOrder(-1000 - $position);

                continue;
            }

            $item->setSortOrder(1000 + $position);
        }
        $this->entityManager->flush();

        foreach ($sourceItems as $position => $sourceItem) {
            $item = $itemsByExternalId[$sourceItem['externalId']] ?? null;
            if (!$item instanceof ProductMedia) {
                $item = new ProductMedia(
                    $tenant,
                    $product,
                    $sourceItem['media'],
                    $position,
                );
                $this->entityManager->persist($item);
                $this->ensureProductMediaMapping(
                    $tenant,
                    $connection,
                    $sourceItem['externalId'],
                    $item,
                );

                continue;
            }

            $item->replaceMedia($sourceItem['media']);
            $item->update($item->getAltText(), $position);
        }

        $manualPosition = count($sourceItems);
        foreach ($existingItems as $item) {
            if (isset($managedItemIds[$item->getId()->toRfc4122()])) {
                continue;
            }

            $item->setSortOrder($manualPosition++);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function syncStock(
        Tenant $tenant,
        Product $product,
        array $attributes,
    ): void {
        if (!is_numeric($attributes['stock'] ?? null)) {
            return;
        }

        $stock = max(0, (float) $attributes['stock']);
        $warehouse = $this->inventoryService->defaultWarehouse(
            $tenant,
            $this->entityManager,
        );
        $this->inventoryService->syncImportedStock(
            $tenant,
            $warehouse,
            $product,
            number_format($stock, 4, '.', ''),
            null,
            $this->entityManager,
        );
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $attributes
     *
     * @return list<string>|null
     */
    private function sourceIdentifiers(
        array $source,
        array $attributes,
        string $attributeKey,
        string $relationship,
    ): ?array {
        if (array_key_exists($attributeKey, $attributes)) {
            return $this->identifiers($attributes[$attributeKey]);
        }
        if (array_key_exists($relationship, $attributes)) {
            return $this->identifiers($attributes[$relationship]);
        }

        $relationships = $source['relationships'] ?? null;
        if (!is_array($relationships) || !array_key_exists($relationship, $relationships)) {
            return null;
        }

        return $this->relationshipIds($source, $relationship);
    }

    /** @param array<string, mixed> $source
     *  @return list<string>
     */
    private function relationshipIds(array $source, string $relationship): array
    {
        $relationships = $source['relationships'] ?? null;
        if (!is_array($relationships)) {
            return [];
        }
        $relation = $relationships[$relationship] ?? null;
        if (!is_array($relation)) {
            return [];
        }

        return $this->identifiers($relation['data'] ?? null);
    }

    /** @return list<string> */
    private function identifiers(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        if (array_key_exists('id', $value)) {
            $id = $this->string($value['id']);

            return $id === null ? [] : [$id];
        }

        $identifiers = [];
        foreach ($value as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : $item;
            $id = $this->string($id);
            if ($id !== null) {
                $identifiers[$id] = $id;
            }
        }

        return array_values($identifiers);
    }

    private function ensureProductMediaMapping(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $externalId,
        ProductMedia $item,
    ): void {
        $mapping = $this->entityManager
            ->getRepository(IntegrationEntityMapping::class)
            ->findOneBy([
                'connection' => $connection,
                'entityType' => 'product_media',
                'externalId' => $externalId,
            ]);
        if (!$mapping instanceof IntegrationEntityMapping) {
            $this->entityManager->persist(new IntegrationEntityMapping(
                $tenant,
                $connection,
                'product_media',
                $externalId,
                $item->getId(),
            ));

            return;
        }

        if ($mapping->getLocalId()->toRfc4122() !== $item->getId()->toRfc4122()) {
            $mapping->remap($item->getId());
        }
    }

    /** @return list<array<string, mixed>> */
    private function relatedItems(mixed $items): array
    {
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
        $mapping = $this->entityManager
            ->getRepository(IntegrationEntityMapping::class)
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

    /** @return list<array<string, mixed>> */
    private function visibility(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $visibility): bool => is_array($visibility),
        ));
    }

    /** @param array<string, mixed> $attributes */
    private function keywords(array $attributes): ?string
    {
        $keywords = $attributes['keywords'] ?? null;
        if (is_array($keywords)) {
            return implode(', ', array_filter(
                $keywords,
                static fn (mixed $keyword): bool => is_string($keyword),
            ));
        }

        return $this->nullable($keywords);
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

    /** @return array<string, bool> */
    private function areas(IntegrationConnection $connection): array
    {
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        $configuredAreas = is_array($settings)
            && is_array($settings['areas'] ?? null)
            ? $settings['areas']
            : [];
        $defaults = [
            'translations' => true,
            'manufacturers' => true,
            'taxes' => true,
            'units' => true,
            'deliveryTimes' => true,
            'properties' => true,
            'tags' => true,
            'categories' => true,
            'prices' => true,
            'stock' => true,
            'media' => true,
            'variants' => true,
            'customFields' => true,
            'channelPublications' => true,
        ];
        foreach ($defaults as $key => $default) {
            $defaults[$key] = is_bool($configuredAreas[$key] ?? null)
                ? $configuredAreas[$key]
                : $default;
        }

        return $defaults;
    }

    /** @return list<string> */
    private function productMatchOrder(IntegrationConnection $connection): array
    {
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        $configuredOrder = is_array($settings)
            && is_array($settings['productMatchOrder'] ?? null)
            ? $settings['productMatchOrder']
            : [];
        $allowed = ['externalId', 'sku', 'ean'];
        $order = array_values(array_filter(
            $configuredOrder,
            static fn (mixed $key): bool => is_string($key)
                && in_array($key, $allowed, true),
        ));

        foreach ($allowed as $key) {
            if (!in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        return $order;
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

    private function productType(mixed $value): string
    {
        return in_array($value, ['physical', 'digital', 'service'], true)
            ? $value
            : 'physical';
    }

    private function nullable(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function localeKey(string $code): string
    {
        return strtolower(str_replace('_', '-', trim($code)));
    }

    private function decimal(mixed $value, string $fallback): string
    {
        return $this->decimalOrNull($value) ?? $fallback;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if (!is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return number_format((float) $value, 4, '.', '');
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function millimeters(mixed $value, int $multiplier = 1): ?int
    {
        return is_numeric($value) ? (int) round((float) $value * $multiplier) : null;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
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
