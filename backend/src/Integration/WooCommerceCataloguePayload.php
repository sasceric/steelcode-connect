<?php

namespace App\Integration;

use App\Entity\CategoryProduct;
use App\Entity\Currency;
use App\Entity\IntegrationConnection;
use App\Entity\Product;
use App\Entity\ProductBrand;
use App\Entity\ProductMedia;
use App\Entity\ProductPrice;
use App\Entity\ProductPropertyAssignment;
use App\Entity\ProductTag;
use App\Entity\ProductTranslation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** Canonical local reference tokens keep previews stable as Woo assigns new IDs. */
final class WooCommerceCataloguePayload
{
    public function __construct(private readonly CatalogueExportReferences $references)
    {
    }

    public static function token(string $type, string $id): string
    {
        return '@'.$type.':'.$id;
    }

    public function externalId(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        string $type,
        string $localId,
        array $settings,
    ): ?string
    {
        if (isset($settings['mappings'][$type][$localId])) {
            return $settings['mappings'][$type][$localId];
        }
        $entity = match ($type) {
            'propertyGroup' => 'property_group',
            default => $type,
        };
        $ids = $manager->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT external_id FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = :type AND local_id = :local',
            ['tenant' => (string) $connection->getTenant()->getId(), 'connection' => (string) $connection->getId(), 'type' => $entity, 'local' => $localId],
        );
        $ids = array_values(array_filter($ids, static fn (string $id): bool =>
            $type === 'tax' ? ctype_digit($id) : CatalogueExportSettings::validTarget($type, $id, 'woocommerce')
        ));

        return count($ids) === 1 ? $ids[0] : null;
    }

    public function build(
        Product $product,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $settings,
        bool $create,
    ): array
    {
        if (!$product->getTenant()->getId()->equals($connection->getTenant()->getId())) {
            throw new \DomainException('Cross-tenant catalogue publication is forbidden.');
        }
        $database = $manager->getConnection();
        $tenant = (string) $connection->getTenant()->getId();
        $parent = $product->getParent();
        $issues = [];
        $dependencies = [];
        $locale = $settings['destinationLocaleId'] ?? '';
        if ($locale !== '' && !$this->references->owns($connection, $manager, 'locale', $locale)) {
            $issues[] = 'The selected catalogue language is not enabled for this tenant.';
        }
        $translation = $this->translation($product, $manager, $locale);
        $parentTranslation = $parent ? $this->translation($parent, $manager, $locale) : null;
        $name = $translation?->getName() ?: $parentTranslation?->getName() ?: $product->getSku() ?: '';
        if ($parent && $name === $product->getSku()) {
            $name = $parentTranslation?->getName() ?: $name;
        }
        $wooType = $translation?->getCustomFields()['_woocommerce']['type'] ?? null;
        if (in_array($wooType, ['external', 'grouped'], true)) {
            $issues[] = 'Grouped and external WooCommerce products require a dedicated adapter; they will not be converted silently to simple products.';
        }
        $hasVariants = !$parent && (bool) $database->fetchOne(
            'SELECT 1 FROM products WHERE tenant_id = :tenant AND parent_id = :product LIMIT 1',
            ['tenant' => $tenant, 'product' => (string) $product->getId()],
        );
        $payload = ['sku' => $product->getSku() ?? ''];
        if ($payload['sku'] === '') {
            $issues[] = 'A product number is required for duplicate-safe publication.';
        }
        if (!$parent) {
            $payload['type'] = $hasVariants ? 'variable' : 'simple';
        }
        if ($parent?->getParent()) {
            $issues[] = 'Nested variation parents are not supported by WooCommerce.';
        }
        if ($create || in_array('content', $settings['fields'], true)) {
            if ($name === '') {
                $issues[] = 'A product name is required.';
            }
            if (!$parent) {
                $payload['name'] = $name;
                $payload['short_description'] = $translation?->getShortDescription() ?? '';
            }
            $payload['description'] = $translation?->getDescription() ?? $parentTranslation?->getDescription() ?? '';
            $payload['global_unique_id'] = $product->getEan() ?? '';
            $payload['virtual'] = $product->getProductType() === 'digital';
            if (!$parent) {
                $payload['featured'] = $product->isFeatured();
            }
        }
        if ($settings['publicationMode'] !== 'keep' || $create) {
            $payload['status'] = $settings['publicationMode'] === 'activate' ? 'publish'
                : ($settings['publicationMode'] === 'deactivate' || !$parent ? 'draft' : 'publish');
        }
        $store = $settings['_woo'] ?? [];
        if (($create || in_array('prices', $settings['fields'], true)) && !$hasVariants) {
            $currencyCode = strtoupper((string) ($store['woocommerce_currency'] ?? ''));
            $currency = $manager->getRepository(Currency::class)->findOneBy(['code' => $currencyCode]);
            if (!$currency instanceof Currency || !$this->references->owns($connection, $manager, 'currency', (string) $currency->getId())) {
                $issues[] = 'The WooCommerce store currency is not enabled for this tenant.';
            } else {
                $price = null;
                foreach ($product->getPrice() ?: $parent?->getPrice() ?: [] as $key => $candidate) {
                    if (is_array($candidate) && (string) ($candidate['currencyId'] ?? $key) === (string) $currency->getId()) {
                        $price = $candidate;
                        break;
                    }
                }
                $side = ($store['woocommerce_prices_include_tax'] ?? 'no') === 'yes' ? 'gross' : 'net';
                if (!is_array($price) || !is_numeric($price[$side] ?? null) || (float) $price[$side] < 0) {
                    $issues[] = 'A selling price in the destination store currency is required; currency conversion is not guessed.';
                } else {
                    $factor = bcadd('1', bcdiv($settings['priceMarkup'], '100', 8), 8);
                    $amount = bcmul((string) $price[$side], $factor, 4);
                    $list = $price['listPrice'][$side] ?? null;
                    $payload['regular_price'] = is_numeric($list) ? bcmul((string) $list, $factor, 4) : $amount;
                    $payload['sale_price'] = is_numeric($list) && bccomp((string) $list, (string) $price[$side], 4) > 0 ? $amount : '';
                    $payload['date_on_sale_from_gmt'] = null;
                    $payload['date_on_sale_to_gmt'] = null;
                    $schedules = $manager->getRepository(ProductPrice::class)->findBy([
                        'tenant' => $connection->getTenant(), 'product' => $product,
                        'currency' => $currency, 'priceType' => 'default', 'quantityStart' => '1.0000',
                    ]);
                    $schedules = array_values(array_filter($schedules, static fn (ProductPrice $entry): bool =>
                        str_starts_with($entry->getPricingContext(), 'woo:') && str_ends_with($entry->getPricingContext(), ':sale')
                    ));
                    if (count($schedules) > 1) {
                        $issues[] = 'Multiple source sale schedules require an explicit pricing decision.';
                    } elseif (count($schedules) === 1) {
                        $schedule = $schedules[0];
                        $scheduled = $schedule->getPrice();
                        if (!is_numeric($scheduled[$side] ?? null)) {
                            $issues[] = 'The scheduled sale has no destination-currency amount.';
                        } else {
                            $payload['sale_price'] = bcmul((string) $scheduled[$side], $factor, 4);
                            if (is_numeric($scheduled['listPrice'][$side] ?? null)) {
                                $payload['regular_price'] = bcmul((string) $scheduled['listPrice'][$side], $factor, 4);
                            }
                            $payload['date_on_sale_from_gmt'] = $schedule->getValidFrom()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s');
                            $payload['date_on_sale_to_gmt'] = $schedule->getValidUntil()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s');
                        }
                    }
                }
            }
        }
        if ($create || in_array('prices', $settings['fields'], true)) {
            $tax = $product->getTax() ?? $parent?->getTax();
            if ($tax === null || (float) $tax->getRate() === 0) {
                $payload['tax_status'] = 'none';
            } else {
                $id = $this->externalId($connection, $manager, 'tax', (string) $tax->getId(), $settings);
                $rates = $store['_taxRates'] ?? [];
                if ($id !== null && ctype_digit($id)) {
                    $matches = array_values(array_filter($rates, static fn (array $rate): bool => (string) $rate['id'] === $id));
                    $id = count($matches) === 1 ? ($matches[0]['class'] ?: 'standard') : null;
                }
                if ($id === null) {
                    $classes = [];
                    foreach ($rates as $rate) {
                        if (empty($rate['compound']) && abs((float) $rate['rate'] - (float) $tax->getRate()) < 0.0001) {
                            $classes[$rate['class'] ?: 'standard'] = true;
                        }
                    }
                    $id = count($classes) === 1 ? array_key_first($classes) : null;
                }
                $classRates = array_values(array_filter($rates, static fn (array $rate): bool => ($rate['class'] ?: 'standard') === $id));
                if ($id === null || $classRates === [] || array_filter($classRates, static fn (array $rate): bool =>
                    !empty($rate['compound']) || abs((float) $rate['rate'] - (float) $tax->getRate()) > 0.0001
                )) {
                    $issues[] = 'The selling tax requires an unambiguous, equal-rate WooCommerce tax-class mapping.';
                } else {
                    $payload['tax_status'] = 'taxable';
                    $payload['tax_class'] = $id === 'standard' ? '' : $id;
                }
            }
            if ($parent !== null) {
                // Woo variation tax status is effectively inherited from its parent.
                $parentTax = $parent->getTax();
                $parentTaxable = $parentTax !== null && (float) $parentTax->getRate() > 0;
                $taxable = $tax !== null && (float) $tax->getRate() > 0;
                if ($taxable !== $parentTaxable) {
                    $issues[] = 'WooCommerce variations inherit their parent tax status. Mixed taxable/non-taxable variants require a dedicated tax-class decision.';
                }
                unset($payload['tax_status']);
            }
        }
        if (in_array('fulfilment', $settings['fields'], true)) {
            $weightFactor = ['g' => 1, 'kg' => 1000, 'lbs' => 453.59237, 'oz' => 28.349523125][$store['woocommerce_weight_unit'] ?? 'kg'] ?? null;
            $lengthFactor = ['mm' => 1, 'cm' => 10, 'm' => 1000, 'in' => 25.4, 'yd' => 914.4][$store['woocommerce_dimension_unit'] ?? 'cm'] ?? null;
            if ($weightFactor === null || $lengthFactor === null) {
                $issues[] = 'Unsupported destination measurement units.';
            } else {
                $payload['weight'] = $product->getWeightGrams() === null ? '' : (string) round($product->getWeightGrams() / $weightFactor, 6);
                $payload['dimensions'] = [];
                foreach (['length' => $product->getLengthMillimeters(), 'width' => $product->getWidthMillimeters(), 'height' => $product->getHeightMillimeters()] as $key => $value) {
                    $payload['dimensions'][$key] = $value === null ? '' : (string) round($value / $lengthFactor, 6);
                }
            }
        }
        $resolve = function (string $type, string $id) use ($connection, $manager, $settings, &$dependencies, &$issues): string {
            return $this->dependency($type, $id, $connection, $manager, $settings, $dependencies, $issues);
        };
        if (!$parent && in_array('classification', $settings['fields'], true)) {
            foreach ([CategoryProduct::class => ['categories', 'category', 'getCategory'], ProductBrand::class => ['brands', 'brand', 'getBrand'], ProductTag::class => ['tags', 'tag', 'getTag']] as $entity => [$key, $type, $getter]) {
                $payload[$key] = [];
                foreach ($manager->getRepository($entity)->findBy(['product' => $product]) as $assignment) {
                    $reference = $assignment->$getter();
                    $payload[$key][] = ['id' => $resolve($type, (string) $reference->getId())];
                }
            }
        }
        if ($parent) {
            $payload['attributes'] = [];
            foreach ($product->getOptionValues() as $groupId => $value) {
                if (!Uuid::isValid((string) $groupId) || ($value !== '*' && !Uuid::isValid((string) $value))) {
                    $issues[] = 'This variation has non-canonical option values; reconcile its properties first.';
                    continue;
                }
                $group = $resolve('propertyGroup', $groupId);
                $payload['attributes'][] = ['id' => $group, 'option' => $value === '*' ? '' : $resolve('property', $value)];
            }
        } elseif ($hasVariants || in_array('classification', $settings['fields'], true)) {
            $groups = [];
            $flags = [];
            $rows = $database->fetchAllAssociative(
                'SELECT DISTINCT p.id, p.property_group_id FROM properties p WHERE p.tenant_id = :tenant AND (
                    EXISTS (SELECT 1 FROM product_property_assignments a WHERE a.property_id = p.id AND a.product_id = :product AND a.tenant_id = :tenant)
                    OR EXISTS (SELECT 1 FROM product_variant_option_values v JOIN products child ON child.id = v.product_id AND child.tenant_id = :tenant WHERE v.property_id = p.id AND v.tenant_id = :tenant AND child.parent_id = :product)
                    OR EXISTS (SELECT 1 FROM products child WHERE child.parent_id = :product AND child.tenant_id = :tenant AND child.option_values::jsonb @> jsonb_build_object(p.property_group_id::text, p.id::text))
                ) ORDER BY p.id LIMIT 501',
                ['tenant' => $tenant, 'product' => (string) $product->getId()],
            );
            if (count($rows) > 500) {
                $issues[] = 'More than 500 attribute values require a larger variation adapter.';
            }
            foreach ($rows as $row) {
                $groups[$row['property_group_id']][$row['id']] = true;
            }
            $defaults = [];
            foreach ($product->getAttributeConfiguration() as $attributes) {
                foreach ($attributes as $attribute) {
                    $group = $attribute['groupId'] ?? null;
                    if (!$group || !Uuid::isValid($group)) {
                        continue;
                    }
                    foreach ($attribute['propertyIds'] ?? [] as $id) {
                        $groups[$group][$id] = true;
                    }
                    $flags[$group] ??= [
                        'visible' => (bool) ($attribute['visible'] ?? true),
                        'variation' => (bool) ($attribute['variation'] ?? false),
                        'position' => (int) ($attribute['position'] ?? 0),
                    ];
                    if (!empty($attribute['defaultPropertyId'])) {
                        $defaults[$group] = $attribute['defaultPropertyId'];
                    }
                }
            }
            $payload['attributes'] = [];
            foreach ($groups as $groupId => $values) {
                $variation = $hasVariants && (bool) $database->fetchOne(
                    'SELECT 1 FROM products child WHERE child.tenant_id = :tenant AND child.parent_id = :parent
                        AND (jsonb_exists(child.option_values::jsonb, :group) OR EXISTS (
                            SELECT 1 FROM product_variant_option_values v JOIN properties p ON p.id = v.property_id AND p.tenant_id = :tenant
                            WHERE v.tenant_id = :tenant AND v.product_id = child.id AND p.property_group_id = :groupId)) LIMIT 1',
                    ['tenant' => $tenant, 'parent' => (string) $product->getId(), 'group' => $groupId, 'groupId' => $groupId],
                );
                $payload['attributes'][] = [
                    'id' => $resolve('propertyGroup', $groupId),
                    'visible' => $flags[$groupId]['visible'] ?? true,
                    'variation' => $variation || ($hasVariants && ($flags[$groupId]['variation'] ?? false)),
                    'position' => $flags[$groupId]['position'] ?? 0,
                    'options' => array_map(static fn (string $id): string => $resolve('property', $id), array_keys($values)),
                ];
            }
            if ($hasVariants) {
                $payload['default_attributes'] = [];
                foreach ($defaults as $groupId => $value) {
                    $payload['default_attributes'][] = ['id' => $resolve('propertyGroup', $groupId), 'option' => $resolve('property', $value)];
                }
            }
        }
        if (in_array('customFields', $settings['fields'], true)) {
            $payload['meta_data'] = [];
            foreach (SourcePayloadSanitizer::sanitize($translation?->getCustomFields() ?? []) as $key => $value) {
                if ($key === '_woocommerce' || str_starts_with($key, '_connect_')) {
                    continue;
                }
                $definition = $database->fetchAssociative(
                    'SELECT id, technical_name, config FROM custom_fields WHERE tenant_id = :tenant AND technical_name = :name',
                    ['tenant' => $tenant, 'name' => $key],
                );
                $config = $definition ? json_decode($definition['config'], true) : [];
                $metaName = $config['originalKey'] ?? $key;
                if ($definition) {
                    $metaName = $settings['mappings']['customField'][$definition['id']] ?? $metaName;
                }
                if ($metaName === '' || str_starts_with($metaName, '_') || SourcePayloadSanitizer::sanitize([$metaName => $value]) === []) {
                    continue; // Never write private WordPress/plugin state as portable fields.
                }
                $payload['meta_data'][] = ['key' => $metaName, 'value' => $value];
            }
        }
        if (in_array('media', $settings['fields'], true)) {
            $images = [];
            foreach ($manager->getRepository(ProductMedia::class)->findBy(['tenant' => $connection->getTenant(), 'product' => $product], ['sortOrder' => 'ASC']) as $item) {
                if ($item->getMediaType() !== 'image') {
                    continue;
                }
                $media = $item->getMedia();
                if ($media->getChecksum() === null) {
                    $issues[] = 'This image has no verified stored checksum. Re-upload or re-import it before publication.';
                }
                $id = (string) $media->getId();
                $dependencies[self::token('media', $id)] = ['type' => 'media', 'localId' => $id, 'payload' => ['checksum' => $media->getChecksum(), 'name' => $media->getFileName()]];
                $images[] = ['id' => self::token('media', $id), 'name' => $item->getFileName(), 'alt' => $item->getAltText() ?? ''];
            }
            if ($parent) {
                $payload['image'] = $images[0] ?? ['id' => 0];
            } else {
                $payload['images'] = $images;
            }
        }

        return [
            'name' => $name,
            'payload' => $payload,
            'issues' => array_values(array_unique($issues)),
            'dependencies' => array_values($dependencies),
            'references' => [],
        ];
    }

    private function translation(Product $product, EntityManagerInterface $manager, string $locale): ?ProductTranslation
    {
        $translations = $manager->getRepository(ProductTranslation::class)->findBy(['product' => $product]);
        foreach ($translations as $translation) {
            if ($locale !== '' ? (string) $translation->getLocale()->getId() === $locale : $translation->getLocale()->getCode() === $product->getTenant()->getDefaultSnippetLocale()) {
                return $translation;
            }
        }

        return $locale === '' ? ($translations[0] ?? null) : null;
    }

    private function dependency(
        string $type,
        string $id,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $settings,
        array &$dependencies,
        array &$issues,
    ): string
    {
        $token = self::token($type, $id);
        if (isset($dependencies[$token])) {
            return $token;
        }
        $table = match ($type) {
            'propertyGroup' => 'property_groups',
            'property' => 'properties',
            'category' => 'categories',
            'brand' => 'brands',
            'tag' => 'tags',
            default => throw new \DomainException('Unsupported Woo reference type.'),
        };
        $row = $manager->getConnection()->fetchAssociative(
            "SELECT * FROM $table WHERE tenant_id = :tenant AND id = :id",
            ['tenant' => (string) $connection->getTenant()->getId(), 'id' => $id],
        );
        if (!$row) {
            $issues[] = 'An attribute or taxonomy reference is unavailable in this tenant.';
            return $token;
        }
        $name = $row['name'] ?? null;
        if (in_array($type, ['category', 'brand'], true)) {
            $name = $manager->getConnection()->fetchOne(
                "SELECT name FROM {$type}_translations WHERE {$type}_id = :id ORDER BY CASE WHEN locale_id::text = :locale THEN 0 ELSE 1 END, locale_id LIMIT 1",
                ['id' => $id, 'locale' => $settings['destinationLocaleId'] ?? ''],
            );
        }
        $payload = [
            'name' => $name ?: $id,
            'slug' => 'connect-'.substr(hash('sha256', $connection->getTenant()->getId().':'.$connection->getId().':'.$type.':'.$id), 0, 24),
        ];
        $dependencies[$token] = ['type' => $type, 'localId' => $id, 'payload' => $payload];
        if ($type === 'category' && !empty($row['parent_id'])) {
            $payload['parent'] = $this->dependency('category', $row['parent_id'], $connection, $manager, $settings, $dependencies, $issues);
        } elseif ($type === 'category') {
            $payload['parent'] = $settings['categoryRootId'] === '' ? 0 : (int) $settings['categoryRootId'];
        } elseif ($type === 'property') {
            $payload['group'] = $this->dependency('propertyGroup', $row['property_group_id'], $connection, $manager, $settings, $dependencies, $issues);
        } elseif ($type === 'propertyGroup') {
            $payload['type'] = 'select';
            $payload['order_by'] = 'menu_order';
        }
        $dependencies[$token]['payload'] = $payload;

        return $token;
    }
}
