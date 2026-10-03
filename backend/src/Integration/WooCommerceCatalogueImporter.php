<?php

namespace App\Integration;

use App\Entity\Category;
use App\Entity\Brand;
use App\Entity\BrandTranslation;
use App\Entity\CategoryProduct;
use App\Entity\CategoryTranslation;
use App\Entity\Currency;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\IntegrationSalesChannel;
use App\Entity\Locale;
use App\Entity\Manufacturer;
use App\Entity\ManufacturerTranslation;
use App\Entity\Product;
use App\Entity\ProductChannelPublication;
use App\Entity\ProductMedia;
use App\Entity\ProductDownload;
use App\Entity\ProductPrice;
use App\Entity\ProductCrossSelling;
use App\Entity\ProductCrossSellingAssignment;
use App\Entity\ProductCrossSellingTranslation;
use App\Entity\ProductPropertyAssignment;
use App\Entity\ProductTag;
use App\Entity\ProductTranslation;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\PropertyGroupTranslation;
use App\Entity\PropertyTranslation;
use App\Entity\Tag;
use App\Entity\Tax;
use App\Entity\TaxTranslation;
use App\Entity\TenantCurrency;
use App\Service\InventoryService;
use App\Service\ProductBrandService;
use App\Service\SeoUrlService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class WooCommerceCatalogueImporter
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ShopwareMediaImporter $mediaImporter,
        private readonly SeoUrlService $seoUrls,
        private readonly ProductBrandService $brands,
        private readonly WooCommerceCustomFieldImporter $customFields,
    )
    {
    }

    public function mapped(
        IntegrationConnection $connection,
        string $type,
        string $externalId,
        string $class,
        EntityManagerInterface $manager,
    ): ?object
    {
        $mapping = $manager->getRepository(IntegrationEntityMapping::class)->findOneBy(
            [
                'tenant' => $connection->getTenant(),
                'connection' => $connection,
                'entityType' => $type,
                'externalId' => $externalId,
            ],
        );
        return $mapping instanceof IntegrationEntityMapping ? $manager->getRepository($class)->findOneBy(['id' => $mapping->getLocalId(), 'tenant' => $connection->getTenant()]) : null;
    }

    private function map(
        IntegrationConnection $connection,
        string $type,
        string $externalId,
        Uuid $localId,
        EntityManagerInterface $manager,
    ): void
    {
        $mapping = $manager->getRepository(IntegrationEntityMapping::class)->findOneBy([
            'tenant' => $connection->getTenant(),
            'connection' => $connection,
            'entityType' => $type,
            'externalId' => $externalId,
        ]);
        if ($mapping instanceof IntegrationEntityMapping) {
            $mapping->remap($localId);
        } else {
            $manager->persist(
                new IntegrationEntityMapping(
                    $connection->getTenant(),
                    $connection,
                    $type,
                    $externalId,
                    $localId,
                ),
            );
        }
    }

    private function locale(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
    ): Locale
    {
        $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => $connection->getTenant()->getDefaultSnippetLocale()]);
        if (!$locale instanceof Locale) {
            throw new \DomainException('The tenant default catalogue locale is missing.');
        }
        return $locale;
    }

    public function reference(
        string $type,
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
    ): bool
    {
        $externalId = (string) $source['id'];
        $tenant = $connection->getTenant();
        $locale = $this->locale($connection, $manager);
        $name = mb_substr(
            html_entity_decode((string) ($source['name'] ?? $source['label'] ?? 'WooCommerce'), ENT_QUOTES | ENT_HTML5),
            0,
            255,
        );
        $created = false;
        if ($type === 'category') {
            $entity = $this->mapped(
                $connection,
                $type,
                $externalId,
                Category::class,
                $manager,
            );
            $created = !$entity instanceof Category;
            $entity ??= new Category($tenant, (int) ($source['menu_order'] ?? 0));
            $manager->persist($entity);
            $translation = $manager->getRepository(CategoryTranslation::class)->findOneBy(['category' => $entity, 'locale' => $locale]);
            $translation ??= new CategoryTranslation($entity, $locale, $name);
            $translation->update(
                $name,
                $source['description'] ?? null,
                null,
                null,
                null,
                [],
            );
            $manager->persist($translation);
            $parent = $this->mapped(
                $connection,
                $type,
                (string) ($source['parent'] ?? 0),
                Category::class,
                $manager,
            );
            $entity->move($parent instanceof Category ? $parent : null, (int) ($source['menu_order'] ?? 0));
            $this->seoUrls->syncCanonical(
                $tenant,
                'category',
                $entity->getId(),
                $locale,
                $name,
                source: 'import',
            );
        } elseif ($type === 'tag') {
            $entity = $this->mapped(
                $connection,
                $type,
                $externalId,
                Tag::class,
                $manager,
            );
            $entity ??= $manager->getRepository(Tag::class)->findOneBy(['tenant' => $tenant, 'name' => mb_substr($name, 0, 100)]);
            $created = !$entity instanceof Tag;
            $entity ??= new Tag($tenant, mb_substr($name, 0, 100));
            $conflict = $manager->getRepository(Tag::class)->findOneBy([
                'tenant' => $tenant,
                'name' => mb_substr($name, 0, 100),
            ]);
            if ($conflict instanceof Tag && !$conflict->getId()->equals($entity->getId())) {
                throw new \DomainException('The renamed WooCommerce tag conflicts with an existing tenant tag.');
            }
            $entity->update(mb_substr($name, 0, 100));
        } elseif ($type === 'brand') {
            $entity = $this->mapped($connection, $type, $externalId, Brand::class, $manager);
            $created = !$entity instanceof Brand;
            $entity ??= new Brand($tenant);
            $entity->update(
                isset($source['slug']) ? mb_substr((string) $source['slug'], 0, 255) : null,
                SourcePayloadSanitizer::sanitize($source),
            );
            $manager->persist($entity);
            $translation = $manager->getRepository(BrandTranslation::class)->findOneBy([
                'brand' => $entity,
                'locale' => $locale,
            ]);
            $translation ??= new BrandTranslation($entity, $locale, $name);
            $translation->update($name, $source['description'] ?? null);
            $manager->persist($translation);
        } elseif ($type === 'manufacturer') {
            $entity = $this->mapped(
                $connection,
                $type,
                $externalId,
                Manufacturer::class,
                $manager,
            );
            $created = !$entity instanceof Manufacturer;
            $entity ??= new Manufacturer($tenant);
            $manager->persist($entity);
            $translation = $manager->getRepository(ManufacturerTranslation::class)->findOneBy(['manufacturer' => $entity, 'locale' => $locale]);
            $translation ??= new ManufacturerTranslation($entity, $locale, $name);
            $translation->update(
                $name,
                $source['description'] ?? null,
                null,
                null,
                null,
                [],
            );
            $manager->persist($translation);
        } elseif ($type === 'tax') {
            $taxName = mb_substr(
                'Woo ' . $name . ' ' . $externalId . ' ' . substr($connection->getId()->toRfc4122(), -8),
                0,
                255,
            );
            $entity = $this->mapped(
                $connection,
                $type,
                $externalId,
                Tax::class,
                $manager,
            );
            $created = !$entity instanceof Tax;
            $rate = number_format(
                (float) ($source['rate'] ?? 0),
                2,
                '.',
                '',
            );
            $entity ??= new Tax($tenant, $taxName, $rate);
            $entity->update($taxName, $rate, true);
            $manager->persist($entity);
            $translation = $manager->getRepository(TaxTranslation::class)->findOneBy(['tax' => $entity, 'locale' => $locale]);
            $translation ??= new TaxTranslation($entity, $locale, $name);
            $translation->update($name);
            $manager->persist($translation);
        } elseif ($type === 'property_group') {
            $entity = $this->mapped(
                $connection,
                $type,
                $externalId,
                PropertyGroup::class,
                $manager,
            );
            $created = !$entity instanceof PropertyGroup;
            $code = 'woo-' . substr($connection->getId()->toRfc4122(), -8) . '-' . $externalId;
            $entity ??= new PropertyGroup(
                $tenant,
                $name,
                $code,
                'text',
                true,
                true,
                'alphanumeric',
                0,
            );
            $entity->update(
                $name,
                $entity->getCode(),
                $entity->getDisplayType(),
                $entity->isFilterable(),
                $entity->isDisplayedOnProductDetail(),
                ($source['order_by'] ?? '') === 'menu_order' ? 'custom' : 'alphanumeric',
                $entity->getPosition(),
            );
            $manager->persist($entity);
            $translation = $manager->getRepository(PropertyGroupTranslation::class)->findOneBy(['propertyGroup' => $entity, 'locale' => $locale]);
            $translation ??= new PropertyGroupTranslation($entity, $locale, $name);
            $translation->update($name);
            $manager->persist($translation);
        } else {
            throw new \InvalidArgumentException('Unsupported WooCommerce reference type.');
        }
        $manager->persist($entity);
        $this->map(
            $connection,
            $type,
            $externalId,
            $entity->getId(),
            $manager,
        );
        return $created;
    }

    public function term(
        string $attributeId,
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
    ): bool
    {
        $group = $this->mapped($connection, 'property_group', $attributeId, PropertyGroup::class, $manager);
        if (!$group instanceof PropertyGroup) {
            throw new \DomainException('Import the attribute before its terms.');
        }
        $name = html_entity_decode((string) $source['name'], ENT_QUOTES | ENT_HTML5);
        $termId = $attributeId . ':term:' . $source['id'];
        $labelId = $attributeId . ':' . substr(hash('sha256', $name), 0, 24);
        $property = $this->mapped($connection, 'property', $termId, Property::class, $manager);
        // Adopt old name-based imports without generating a second property.
        $property ??= $this->mapped($connection, 'property', $labelId, Property::class, $manager);
        $created = !$property instanceof Property;
        $property ??= new Property(
            $connection->getTenant(),
            $group,
            mb_substr($name, 0, 255),
            'woo-' . substr(hash('sha256', $termId), 0, 24),
            null,
            (int) ($source['menu_order'] ?? 0),
        );
        $property->update(
            mb_substr($name, 0, 255),
            $property->getCode(),
            $property->getColorHex(),
            (int) ($source['menu_order'] ?? 0),
            $property->getMedia(),
        );
        $manager->persist($property);
        $locale = $this->locale($connection, $manager);
        $translation = $manager->getRepository(PropertyTranslation::class)->findOneBy(['property' => $property, 'locale' => $locale]);
        $translation ??= new PropertyTranslation($property, $locale, mb_substr($name, 0, 255));
        $translation->update(mb_substr($name, 0, 255));
        $manager->persist($translation);
        $labels = array_values(array_unique([$name, (string) ($source['slug'] ?? $name), mb_strtolower($name)]));
        $labelIds = array_map(
            static fn(string $label): string => $attributeId . ':' . substr(hash('sha256', $label), 0, 24),
            $labels,
        );
        foreach ($manager->getRepository(IntegrationEntityMapping::class)->findBy([
            'tenant' => $connection->getTenant(),
            'connection' => $connection,
            'entityType' => 'property_label',
            'localId' => $property->getId(),
        ]) as $alias) {
            if (!in_array($alias->getExternalId(), $labelIds, true)) {
                $manager->remove($alias);
            }
        }
        $this->map($connection, 'property', $termId, $property->getId(), $manager);
        foreach ($labelIds as $id) {
            $this->map($connection, 'property_label', $id, $property->getId(), $manager);
        }

        return $created;
    }

    private function option(
        IntegrationConnection $connection,
        array $attribute,
        string $name,
        EntityManagerInterface $manager,
    ): Property
    {
        $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5);
        $groupId = (int) ($attribute['id'] ?? 0);
        $externalId = $groupId > 0 ? (string) $groupId : 'local-' . substr(hash('sha256', (string) $attribute['name']), 0, 24);
        $group = $this->mapped(
            $connection,
            'property_group',
            $externalId,
            PropertyGroup::class,
            $manager,
        );
        if (!$group instanceof PropertyGroup) {
            $this->reference(
                'property_group',
                ['id' => $externalId, 'name' => $attribute['name']],
                $connection,
                $manager,
            );
            $manager->flush();
            $group = $this->mapped(
                $connection,
                'property_group',
                $externalId,
                PropertyGroup::class,
                $manager,
            );
        }
        $propertyId = $externalId . ':' . substr(hash('sha256', $name), 0, 24);
        $property = $this->mapped(
            $connection,
            'property_label',
            $propertyId,
            Property::class,
            $manager,
        );
        $property ??= $this->mapped(
            $connection,
            'property',
            $propertyId,
            Property::class,
            $manager,
        );
        if (!$property instanceof Property) {
            if ($groupId > 0) {
                throw new \DomainException('A WooCommerce attribute term is not mapped: ' . $name . '. Import its terms before products.');
            }
            $property = new Property(
                $connection->getTenant(),
                $group,
                mb_substr($name, 0, 255),
                'woo-' . substr(hash('sha256', $propertyId), 0, 24),
                null,
                0,
            );
            $manager->persist($property);
            $manager->persist(
                new PropertyTranslation($property, $this->locale($connection, $manager), mb_substr($name, 0, 255)),
            );
            $this->map(
                $connection,
                'property',
                $propertyId,
                $property->getId(),
                $manager,
            );
            $manager->flush();
        }
        return $property;
    }

    public function product(
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $store,
        array $areas,
        ?string $parentId = null,
    ): bool
    {
        $tenant = $connection->getTenant();
        $externalId = (string) $source['id'];
        $parent = $parentId !== null ? $this->mapped(
            $connection,
            'product',
            $parentId,
            Product::class,
            $manager,
        ) : null;
        if ($parentId !== null && !$parent instanceof Product) {
            throw new \DomainException('The WooCommerce variant parent has not imported successfully.');
        }
        $sourceSku = trim((string) ($source['sku'] ?? ''));
        // Woo returns the parent's SKU when a variation has no own SKU.
        // That inherited value must never merge the variation into its parent.
        if ($parent instanceof Product && $sourceSku === $parent->getSku()) {
            $sourceSku = '';
        }
        $ean = trim((string) ($source['global_unique_id'] ?? ''));
        $product = $this->mapped(
            $connection,
            'product',
            $externalId,
            Product::class,
            $manager,
        );
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        if ($parent instanceof Product && $product instanceof Product && $product->getId()->equals($parent->getId())) {
            $product = null;
        }
        foreach ($settings['productMatchOrder'] ?? ['externalId', 'sku', 'ean'] as $match) {
            if ($product instanceof Product) {
                break;
            }
            $value = $match === 'sku' ? $sourceSku : ($match === 'ean' ? $ean : '');
            if ($value === '') {
                continue;
            }
            $matches = $manager->getRepository(Product::class)->findBy(['tenant' => $tenant, $match => $value], null, 2);
            if (count($matches) > 1) {
                throw new \DomainException('The source identifier matches multiple tenant products.');
            }
            $candidate = $matches[0] ?? null;
            if ($candidate instanceof Product && $parent instanceof Product && $candidate->getId()->equals($parent->getId())) {
                continue;
            }
            $product = $candidate;
        }
        $created = !$product instanceof Product;
        $product ??= new Product($tenant);
        $manager->persist($product);
        $locale = $this->locale($connection, $manager);
        $translation = $manager->getRepository(ProductTranslation::class)->findOneBy([
            'product' => $product,
            'locale' => $locale,
        ]);
        $previousSource = $translation?->getCustomFields()['_woocommerce'] ?? [];
        $sku = $sourceSku !== '' ? $sourceSku : $product->getSku() ?? 'WOO-' . substr($connection->getId()->toRfc4122(), -8) . '-' . $externalId;
        $options = [];
        $assignedProperties = [];
        $attributeConfiguration = [];
        $sourceKey = $connection->getId()->toRfc4122();
        if ($parent instanceof Product || ($areas['properties'] ?? true)) {
            foreach ($source['attributes'] ?? [] as $attribute) {
                $selection = null;
                $defaultOption = null;
                foreach ($source['default_attributes'] ?? [] as $default) {
                    $sameGroup = (int) ($attribute['id'] ?? 0) > 0
                        ? (int) ($default['id'] ?? 0) === (int) $attribute['id']
                        : ($default['name'] ?? null) === ($attribute['name'] ?? null);
                    if ($sameGroup && trim((string) ($default['option'] ?? '')) !== '') {
                        $defaultOption = $this->option($connection, $attribute, (string) $default['option'], $manager);
                    }
                }
                // An empty variation option means "Any value", not a new term.
                if ($parent instanceof Product && array_key_exists('option', $attribute) && trim((string) $attribute['option']) === '') {
                    $groupKey = (int) ($attribute['id'] ?? 0) > 0
                        ? (string) $attribute['id']
                        : 'local-' . substr(hash('sha256', (string) $attribute['name']), 0, 24);
                    $group = $this->mapped($connection, 'property_group', $groupKey, PropertyGroup::class, $manager);
                    if (!$group instanceof PropertyGroup) {
                        throw new \DomainException('The wildcard variation attribute is not mapped.');
                    }
                    $options[(string) $group->getId()] = '*';
                    $selection = [
                        'groupId' => (string) $group->getId(),
                        'position' => (int) ($attribute['position'] ?? 0),
                        'visible' => (bool) ($attribute['visible'] ?? true),
                        'variation' => true,
                        'propertyIds' => [],
                        'defaultPropertyId' => null,
                        'wildcard' => true,
                    ];
                }
                foreach ($attribute['options'] ?? (isset($attribute['option']) ? [$attribute['option']] : []) as $value) {
                    if (trim((string) $value) === '') {
                        continue;
                    }
                    $property = $this->option(
                        $connection,
                        $attribute,
                        (string) $value,
                        $manager,
                    );
                    $options[$property->getPropertyGroup()->getId()->toRfc4122()] = $property->getId()->toRfc4122();
                    $selection ??= [
                        'groupId' => (string) $property->getPropertyGroup()->getId(),
                        'position' => (int) ($attribute['position'] ?? 0),
                        'visible' => (bool) ($attribute['visible'] ?? true),
                        'variation' => (bool) ($attribute['variation'] ?? $parent instanceof Product),
                        'propertyIds' => [],
                        'defaultPropertyId' => null,
                    ];
                    $selection['propertyIds'][] = (string) $property->getId();
                    if ($defaultOption?->getId()->equals($property->getId())) {
                        $selection['defaultPropertyId'] = (string) $property->getId();
                    }
                    if (!($areas['properties'] ?? true)) {
                        continue;
                    }
                    $propertyKey = $property->getId()->toRfc4122();
                    if (isset($assignedProperties[$propertyKey])) {
                        continue;
                    }
                    $assignedProperties[$propertyKey] = true;
                    $assignment = $manager->getRepository(ProductPropertyAssignment::class)->findOneBy(['product' => $product, 'property' => $property]);
                    if (!$assignment instanceof ProductPropertyAssignment) {
                        $assignment = new ProductPropertyAssignment(
                            $tenant,
                            $product,
                            $property,
                            'woocommerce',
                        );
                        $assignment->initializeSource($sourceKey);
                        $manager->persist($assignment);
                    }
                    $assignment->claimSource($sourceKey);
                }
                if ($selection !== null) {
                    $selection['propertyIds'] = array_values(array_unique($selection['propertyIds']));
                    $attributeConfiguration[] = $selection;
                }
            }
        }
        if ($areas['properties'] ?? true) {
            $product->updateAttributeConfiguration($sourceKey, $attributeConfiguration);
            foreach ($manager->getRepository(ProductPropertyAssignment::class)->findBy(['tenant' => $tenant, 'product' => $product]) as $assignment) {
                if (!isset($assignedProperties[(string) $assignment->getProperty()->getId()]) && $assignment->releaseSource($sourceKey)) {
                    $manager->remove($assignment);
                }
            }
        }
        if ($parent instanceof Product) {
            $prices = $product->getPrice();
            $purchasePrices = $product->getPurchasePrice();
            $cheapestPrices = $product->getCheapestPrice();
            $product->inheritCatalogDataFrom($parent);
            // Parent inheritance must not overwrite independent variant prices
            // or change prices when that import scope is disabled.
            $product->updatePrices($prices, $purchasePrices, $cheapestPrices);
            $product->makeChildOf(
                $parent,
                $sku,
                $ean !== '' ? $ean : null,
                $options,
            );
        } else {
            if ($product->getParent()?->getId()->equals($product->getId())) {
                $product->clearVariantRelationship();
            }
            $product->updateIdentity($sku, $ean !== '' ? $ean : null);
        }
        $name = html_entity_decode(trim((string) ($source['name'] ?? '')), ENT_QUOTES | ENT_HTML5);
        if ($name === '' && $parent instanceof Product) {
            $parentTranslation = $manager->getRepository(ProductTranslation::class)->findOneBy(['product' => $parent, 'locale' => $this->locale($connection, $manager)]);
            $name = $parentTranslation?->getName() ?? $sku;
        }
        if ($name === '') {
            throw new \InvalidArgumentException('WooCommerce product has no name.');
        }
        $type = !empty($source['virtual']) ? 'service' : 'physical';
        $product->updateCommerce(
            $type,
            $product->getManufacturerNumber(),
            $source['shipping_class'] ?? null,
            $product->getDeliveryTime(),
            $product->getReleaseDate(),
            (bool) ($source['featured'] ?? false),
            $product->getVisibility(),
        );
        $product->updateStatus(($source['status'] ?? 'publish') === 'publish' ? 'active' : 'draft');
        $weightMultiplier = [
            'kg' => 1000,
            'g' => 1,
            'lbs' => 453.59237,
            'oz' => 28.349523125,
        ][$store['woocommerce_weight_unit'] ?? 'kg'] ?? null;
        $dimensionMultiplier = [
            'm' => 1000,
            'cm' => 10,
            'mm' => 1,
            'in' => 25.4,
            'yd' => 914.4,
        ][$store['woocommerce_dimension_unit'] ?? 'cm'] ?? null;
        $convert = static fn(
            mixed $value,
            ?float $multiplier,
        ): ?int => is_numeric($value) && $multiplier !== null ? (int) round((float) $value * $multiplier) : null;
        $dimensions = $source['dimensions'] ?? [];
        $maxPurchase = $product->getMaxPurchaseQuantity();
        if (!empty($source['sold_individually'])) {
            $maxPurchase = '1.0000';
        } elseif (!empty($previousSource['sold_individually']) && $maxPurchase === '1.0000') {
            $maxPurchase = null;
        }
        $product->updateFulfilment(
            $product->getMinPurchaseQuantity(),
            $product->getPurchaseSteps(),
            $maxPurchase,
            $product->getRestockTimeDays(),
            $product->isClearanceSale(),
            $product->isFreeShipping(),
            $product->getSearchKeywords(),
            $convert($source['weight'] ?? null, $weightMultiplier) ?? $parent?->getWeightGrams(),
            $convert($dimensions['length'] ?? null, $dimensionMultiplier) ?? $parent?->getLengthMillimeters(),
            $convert($dimensions['width'] ?? null, $dimensionMultiplier) ?? $parent?->getWidthMillimeters(),
            $convert($dimensions['height'] ?? null, $dimensionMultiplier) ?? $parent?->getHeightMillimeters(),
        );
        $this->map(
            $connection,
            'product',
            $externalId,
            $product->getId(),
            $manager,
        );
        $translation ??= new ProductTranslation($product, $locale, mb_substr($name, 0, 255));
        $fields = $translation->getCustomFields();
        $previousSource = $fields['_woocommerce'] ?? [];
        if ($areas['customFields'] ?? true) {
            $fields = $this->customFields->values($connection, 'product', $source['meta_data'] ?? [], $manager, $fields);
            $fields['_woocommerce'] = SourcePayloadSanitizer::sanitize($source);
        }
        if ($created || ($areas['translations'] ?? true)) {
            $translation->update(
                mb_substr($name, 0, 255),
                $source['short_description'] ?? null,
                $source['description'] ?? null,
                $translation->getMetaTitle(),
                $translation->getMetaDescription(),
                $translation->getMetaKeywords(),
                $fields,
            );
        } elseif ($areas['customFields'] ?? true) {
            $translation->update(
                $translation->getName(),
                $translation->getShortDescription(),
                $translation->getDescription(),
                $translation->getMetaTitle(),
                $translation->getMetaDescription(),
                $translation->getMetaKeywords(),
                $fields,
            );
        }
        $manager->persist($translation);
        $this->seoUrls->syncCanonical(
            $tenant,
            'product',
            $product->getId(),
            $locale,
            $name,
            source: 'import',
        );
        if ($areas['manufacturers'] ?? true) {
            $this->assignBrands($product, $source, $connection, $manager);
        }
        $assignedCategories = [];
        foreach ($source['categories'] ?? [] as $categorySource) {
            if (!($areas['categories'] ?? true)) {
                break;
            }
            $category = $this->mapped(
                $connection,
                'category',
                (string) $categorySource['id'],
                Category::class,
                $manager,
            );
            if (!$category instanceof Category) {
                throw new \DomainException('A WooCommerce category is not mapped.');
            }
            $assignedCategories[(string) $category->getId()] = true;
            $assignment = $manager->getRepository(CategoryProduct::class)->findOneBy(['category' => $category, 'product' => $product]);
            if (!$assignment instanceof CategoryProduct) {
                $assignment = new CategoryProduct($category, $product, 0);
                $assignment->initializeSource($sourceKey);
                $manager->persist($assignment);
            }
            $assignment->claimSource($sourceKey);
        }
        if ($areas['categories'] ?? true) {
            foreach ($manager->getRepository(CategoryProduct::class)->findBy(['product' => $product]) as $assignment) {
                if (!isset($assignedCategories[(string) $assignment->getCategory()->getId()]) && $assignment->releaseSource($sourceKey)) {
                    $manager->remove($assignment);
                }
            }
        }
        $assignedTags = [];
        foreach ($source['tags'] ?? [] as $tagSource) {
            if (!($areas['tags'] ?? true)) {
                break;
            }
            $tag = $this->mapped(
                $connection,
                'tag',
                (string) $tagSource['id'],
                Tag::class,
                $manager,
            );
            if (!$tag instanceof Tag) {
                throw new \DomainException('A WooCommerce tag is not mapped.');
            }
            $assignedTags[(string) $tag->getId()] = true;
            $assignment = $manager->getRepository(ProductTag::class)->findOneBy(['product' => $product, 'tag' => $tag]);
            if (!$assignment instanceof ProductTag) {
                $assignment = new ProductTag($product, $tag);
                $assignment->initializeSource($sourceKey);
                $manager->persist($assignment);
            }
            $assignment->claimSource($sourceKey);
        }
        if ($areas['tags'] ?? true) {
            foreach ($manager->getRepository(ProductTag::class)->findBy(['product' => $product]) as $assignment) {
                if (!isset($assignedTags[(string) $assignment->getTag()->getId()]) && $assignment->releaseSource($sourceKey)) {
                    $manager->remove($assignment);
                }
            }
        }
        if (($areas['prices'] ?? true) && is_numeric($source['price'] ?? null)) {
            $this->price(
                $product,
                $source,
                $connection,
                $manager,
                $store,
            );
        }
        if ($areas['channelPublications'] ?? true) {
            $channel = $manager->getRepository(IntegrationSalesChannel::class)->findOneBy(['connection' => $connection, 'externalId' => 'store']);
            if (!$channel instanceof IntegrationSalesChannel) {
                $channel = new IntegrationSalesChannel(
                    $tenant,
                    $connection,
                    'store',
                    $connection->getName(),
                    'storefront',
                    true,
                    [],
                );
                $manager->persist($channel);
                $manager->flush();
            }
            $publication = $manager->getRepository(ProductChannelPublication::class)->findOneBy(['salesChannel' => $channel, 'product' => $product]);
            $visibility = ($source['status'] ?? 'publish') === 'publish' && ($source['catalog_visibility'] ?? 'visible') !== 'hidden' ? 30 : 0;
            if ($publication instanceof ProductChannelPublication) {
                $publication->update($visibility, $externalId);
            } else {
                $manager->persist(
                    new ProductChannelPublication(
                        $tenant,
                        $product,
                        $channel,
                        $visibility,
                        $externalId,
                    ),
                );
            }
        }
        if (($source['manage_stock'] ?? false) === true && is_numeric($source['stock_quantity'] ?? null) && ($source['type'] ?? '') !== 'variable') {
            $warehouse = $this->inventory->defaultWarehouse($tenant, $manager);
            $this->inventory->syncImportedStock(
                $tenant,
                $warehouse,
                $product,
                number_format(
                    max(0, (float) $source['stock_quantity']),
                    4,
                    '.',
                    '',
                ),
                'WooCommerce opening stock',
                $manager,
            );
        }
        $images = ($areas['media'] ?? true)
            ? $source['images'] ?? (isset($source['image']) && is_array($source['image']) ? [$source['image']] : [])
            : [];
        $assignedMedia = [];
        foreach ($images as $position => $image) {
            if (empty($image['id']) || empty($image['src'])) {
                continue;
            }
            $unchanged = false;
            foreach ($previousSource['images'] ?? (isset($previousSource['image']) ? [$previousSource['image']] : []) as $previousImage) {
                if ($previousImage === $image) {
                    $unchanged = true;
                    break;
                }
            }
            $media = $this->mediaImporter->importConnectorImage(
                $connection,
                'woo-img-' . substr(hash('sha256', $image['id'] . '|' . $image['src'] . '|' . ($image['date_modified_gmt'] ?? $image['date_modified'] ?? '')), 0, 48),
                $image['src'],
                $image['name'] ?? null,
                $unchanged ? (string) $image['id'] : null,
            );
            if ($media === null) {
                continue;
            }
            $manager->flush();
            $item = $manager->getRepository(ProductMedia::class)->findOneBy(['product' => $product, 'media' => $media]);
            if (!$item instanceof ProductMedia) {
                $last = $manager->getRepository(ProductMedia::class)->findOneBy(['product' => $product], ['sortOrder' => 'DESC']);
                $item = new ProductMedia(
                    $tenant,
                    $product,
                    $media,
                    $last instanceof ProductMedia ? $last->getSortOrder() + 1 : 0,
                );
                $manager->persist($item);
                $item->initializeSource($sourceKey);
            }
            $item->claimSource($sourceKey);
            $assignedMedia[(string) $item->getId()] = true;
        }
        if ($areas['media'] ?? true) {
            $kept = [];
            $occupied = [];
            foreach ($manager->getRepository(ProductMedia::class)->findBy(['tenant' => $tenant, 'product' => $product]) as $item) {
                if (!isset($assignedMedia[(string) $item->getId()]) && $item->releaseSource($sourceKey)) {
                    $manager->remove($item);
                } elseif ($item->getSourceKeys() === [$sourceKey]) {
                    $kept[(string) $item->getId()] = $item;
                    $item->setSortOrder(-1000000 - count($kept));
                } else {
                    $occupied[$item->getSortOrder()] = true;
                }
            }
            $manager->flush();
            $position = 0;
            foreach (array_keys($assignedMedia) as $id) {
                if (!isset($kept[$id])) {
                    continue;
                }
                while (isset($occupied[$position])) {
                    ++$position;
                }
                $kept[$id]->setSortOrder($position++);
            }
        }
        if ($areas['productDownloads'] ?? true) {
            $this->downloads($product, $source, $connection, $manager);
        }
        return $created;
    }

    public function assignBrands(
        Product $product,
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
    ): void
    {
        if (!$product->getTenant()->getId()->equals($connection->getTenant()->getId())) {
            throw new \DomainException('Product and connection must belong to the same tenant.');
        }
        $brands = [];
        if (array_key_exists('brands', $source)) {
            foreach ($source['brands'] as $sourceBrand) {
                $brand = $this->mapped(
                    $connection,
                    'brand',
                    (string) $sourceBrand['id'],
                    Brand::class,
                    $manager,
                );
                if (!$brand instanceof Brand) {
                    throw new \DomainException('WooCommerce brand '.$sourceBrand['id'].' is not mapped. Import brand references before products.');
                }
                $brands[] = $brand;
            }
        } elseif ($product->getParent() instanceof Product) {
            $brands = $this->brands->brands($product->getParent(), $manager);
        }
        $this->brands->synchronize(
            $product,
            $brands,
            $connection->getId()->toRfc4122(),
            $manager,
        );
    }

    private function downloads(
        Product $product,
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
    ): void
    {
        $sourceKey = (string) $connection->getId();
        $desired = [];
        foreach (!empty($source['downloadable']) ? ($source['downloads'] ?? []) : [] as $position => $download) {
            if (empty($download['id']) || empty($download['file'])) {
                throw new \DomainException('A WooCommerce download has no identifier or file URL.');
            }
            $media = $this->mediaImporter->importConnectorFile(
                $connection,
                'woo-file-' . substr(hash('sha256', $download['id'] . '|' . $download['file']), 0, 48),
                $download['file'],
                $download['name'] ?? null,
            );
            if ($media === null) {
                throw new \DomainException('A WooCommerce download has an unsupported file type.');
            }
            $manager->flush();
            $item = $manager->getRepository(ProductDownload::class)->findOneBy([
                'tenant' => $connection->getTenant(),
                'product' => $product,
                'media' => $media,
            ]);
            if (!$item instanceof ProductDownload) {
                $item = new ProductDownload(
                    $connection->getTenant(),
                    $product,
                    $media,
                    mb_substr($download['name'] ?? '', 0, 255),
                    $position,
                );
                $item->initializeSource($sourceKey);
                $manager->persist($item);
            } elseif (!in_array('manual', $item->getSourceKeys(), true)) {
                $item->update($media, mb_substr($download['name'] ?? '', 0, 255), $position);
            }
            $item->claimSource($sourceKey);
            $desired[(string) $item->getId()] = true;
        }
        foreach ($manager->getRepository(ProductDownload::class)->findBy(['tenant' => $connection->getTenant(), 'product' => $product]) as $item) {
            if (!isset($desired[(string) $item->getId()]) && $item->releaseSource($sourceKey)) {
                $manager->remove($item);
            }
        }
    }

    /** Run after product mappings exist; never silently drop a forward reference. */
    public function relatedProducts(
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
    ): bool
    {
        $product = $this->mapped($connection, 'product', (string) $source['id'], Product::class, $manager);
        if (!$product instanceof Product) {
            throw new \DomainException('The related-products owner is not mapped.');
        }
        foreach (['cross_sell_ids' => 'Cross-sells', 'upsell_ids' => 'Up-sells'] as $key => $name) {
            $sourceId = 'woo:' . $connection->getId() . ':' . $key;
            $products = [];
            foreach (array_unique($source[$key] ?? []) as $externalId) {
                $related = $this->mapped($connection, 'product', (string) $externalId, Product::class, $manager);
                if (!$related instanceof Product) {
                    throw new \DomainException('A WooCommerce related product is not mapped: ' . $externalId);
                }
                if (!$related->getId()->equals($product->getId())) {
                    $products[(string) $related->getId()] = $related;
                }
            }
            $group = $manager->getRepository(ProductCrossSelling::class)->findOneBy([
                'tenant' => $connection->getTenant(),
                'product' => $product,
                'sourceId' => $sourceId,
            ]);
            if ($products === []) {
                if ($group instanceof ProductCrossSelling) {
                    $manager->remove($group);
                }
                continue;
            }
            $group ??= new ProductCrossSelling(
                $connection->getTenant(),
                $product,
                $sourceId,
                $name,
                'productList',
                true,
                $key === 'cross_sell_ids' ? 0 : 1,
                null,
            );
            $manager->persist($group);
            $locale = $this->locale($connection, $manager);
            $translation = $manager->getRepository(ProductCrossSellingTranslation::class)->findOneBy(['crossSelling' => $group, 'locale' => $locale]);
            $translation ??= new ProductCrossSellingTranslation($group, $locale, $name);
            $manager->persist($translation);
            $existing = [];
            foreach ($manager->getRepository(ProductCrossSellingAssignment::class)->findBy(['crossSelling' => $group]) as $assignment) {
                $id = (string) $assignment->getAssignedProduct()->getId();
                if (!isset($products[$id])) {
                    $manager->remove($assignment);
                } else {
                    $existing[$id] = $assignment;
                }
            }
            foreach (array_values($products) as $position => $related) {
                $assignment = $existing[(string) $related->getId()] ?? new ProductCrossSellingAssignment($group, $related, $position);
                $assignment->update($position);
                $manager->persist($assignment);
            }
        }

        return false;
    }

    private function price(
        Product $product,
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $store,
    ): void
    {
        $code = strtoupper((string) ($store['woocommerce_currency'] ?? ''));
        $currency = $manager->getRepository(Currency::class)->findOneBy(['code' => $code]);
        if (!$currency instanceof Currency) {
            throw new \DomainException('The WooCommerce store currency is not registered in Connect.');
        }
        if (!$manager->getRepository(TenantCurrency::class)->findOneBy(['tenant' => $connection->getTenant(), 'currency' => $currency])) {
            $manager->persist(new TenantCurrency($connection->getTenant(), $currency));
        }
        $amount = (float) $source['price'];
        $taxClass = $source['tax_class'] ?? '';
        $candidates = array_values(
            array_filter(
                $store['_taxRates'] ?? [],
                static fn(array $rate): bool => ($rate['class'] ?? '' ?: 'standard') === ($taxClass === '' ? 'standard' : $taxClass) && empty($rate['compound']),
            ),
        );
        $taxRate = count($candidates) === 1 && ($source['tax_status'] ?? 'taxable') === 'taxable' ? (float) $candidates[0]['rate'] : 0;
        $parentTax = $taxClass === 'parent' ? $product->getParent()?->getTax() : null;
        if ($parentTax instanceof Tax && ($source['tax_status'] ?? 'taxable') === 'taxable') {
            $taxRate = (float) $parentTax->getRate();
        }
        if (
            ($store['woocommerce_calc_taxes'] ?? 'no') === 'yes'
            && ($source['tax_status'] ?? 'taxable') === 'taxable'
            && count($candidates) !== 1
            && !$parentTax instanceof Tax
        ) {
            throw new \DomainException(
                'The WooCommerce product tax class requires a country-specific or compound tax mapping; no rate was guessed.',
            );
        }
        $tax = $parentTax ?? ($taxRate > 0 ? $this->mapped(
            $connection,
            'tax',
            (string) $candidates[0]['id'],
            Tax::class,
            $manager,
        ) : null);
        if ($tax instanceof Tax) {
            $product->updateReferences(
                $tax,
                $product->getUnit(),
                $product->getPurchaseUnit(),
                $product->getReferenceUnit(),
                $product->getDeliveryTimeReference(),
            );
        }
        $included = ($store['woocommerce_prices_include_tax'] ?? 'no') === 'yes';
        $pair = static fn(float $value): array => [
            'gross' => round($included ? $value : $value * (1 + $taxRate / 100), 4),
            'net' => round($included ? $value / (1 + $taxRate / 100) : $value, 4),
            'linked' => true,
        ];
        $price = $pair($amount) + ['currencyId' => $currency->getId()->toRfc4122(), 'currencyCode' => $code];
        if (!empty($source['on_sale']) && is_numeric($source['regular_price'] ?? null)) {
            $price['listPrice'] = $pair((float) $source['regular_price']);
        }
        $prices = $product->getPrice();
        $prices[$currency->getId()->toRfc4122()] = $price;
        $product->updatePrices($prices, $product->getPurchasePrice(), $product->getCheapestPrice());
        $context = 'woo:' . $connection->getId() . ':sale';
        $schedule = $manager->getRepository(ProductPrice::class)->findOneBy([
            'tenant' => $connection->getTenant(),
            'product' => $product,
            'currency' => $currency,
            'priceType' => 'default',
            'pricingContext' => $context,
            'quantityStart' => '1.0000',
        ]);
        $from = $source['date_on_sale_from_gmt'] ?? null;
        $until = $source['date_on_sale_to_gmt'] ?? null;
        if (is_numeric($source['sale_price'] ?? null) && ($from || $until)) {
            $entry = $pair((float) $source['sale_price']) + ['currencyId' => (string) $currency->getId(), 'currencyCode' => $code];
            if (is_numeric($source['regular_price'] ?? null)) {
                $entry['listPrice'] = $pair((float) $source['regular_price']);
            }
            $sale = [(string) $currency->getId() => $entry];
            // Advanced prices use the same decimal amounts as Product.price.
            $schedule ??= new ProductPrice(
                $connection->getTenant(),
                $product,
                $currency,
                'default',
                $sale,
                number_format($taxRate, 2, '.', ''),
            );
            $schedule->updatePrice($sale, number_format($taxRate, 2, '.', ''));
            $schedule->updateAdvanced(
                '1.0000',
                null,
                $context,
                $from ? new \DateTimeImmutable($from, new \DateTimeZone('UTC')) : null,
                $until ? new \DateTimeImmutable($until, new \DateTimeZone('UTC')) : null,
                'woocommerce',
            );
            if ($schedule->getValidFrom() && $schedule->getValidUntil() && $schedule->getValidFrom() > $schedule->getValidUntil()) {
                throw new \DomainException('WooCommerce sale end precedes its start.');
            }
            $manager->persist($schedule);
        } elseif ($schedule instanceof ProductPrice) {
            $manager->remove($schedule);
        }
    }
}
