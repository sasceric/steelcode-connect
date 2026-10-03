<?php

namespace App\Integration;

use App\Entity\CategoryProduct;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductPropertyAssignment;
use App\Entity\ProductTranslation;
use App\Entity\ProductVariantOptionValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final class ShopwareCataloguePayload
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDirectory,
        private readonly CatalogueExportReferences $references,
    )
    {
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
        $mappings = $manager->getRepository(IntegrationEntityMapping::class)->findBy([
            'tenant' => $connection->getTenant(),
            'connection' => $connection,
            'entityType' => match ($type) {
                'deliveryTime' => 'delivery_time',
                'customField' => 'custom_field',
                default => $type,
            },
            'localId' => Uuid::fromString($localId),
        ]);
        $ids = array_values(array_unique(array_map(
            static fn (IntegrationEntityMapping $mapping): string => $mapping->getExternalId(),
            $mappings,
        )));

        return count($ids) === 1 && preg_match('/^[a-f0-9]{32}$/i', $ids[0]) ? strtolower($ids[0]) : null;
    }

    public function productId(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        Product $product,
        array $settings,
    ): string
    {
        return $this->externalId($connection, $manager, 'product', (string) $product->getId(), $settings)
            ?? str_replace('-', '', Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), 'connect:'.$connection->getTenant()->getId().':'.$connection->getId().':product:'.$product->getId())->toRfc4122());
    }

    /** The preview and publisher use precisely the same typed payload builder. */
    public function build(
        Product $product,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $settings,
        bool $create,
    ): array
    {
        if ($product->getTenant()->getId() != $connection->getTenant()->getId()) {
            throw new \DomainException('The export product belongs to another tenant.');
        }
        $issues = [];
        $references = [];
        $expectedReferences = [];
        $dependencies = [];
        $planner = new ShopwareCatalogueDependencies();
        $resolve = function (string $type, ?string $localId) use ($connection, $manager, $settings, &$issues, &$references, &$expectedReferences, &$dependencies, $planner): ?string {
            if ($localId === null) {
                return null;
            }
            if (!$this->references->owns($connection, $manager, $type, $localId)) {
                $issues[] = 'The '.$type.' reference is not available in this tenant: '.$localId;
                return null;
            }
            $id = $this->externalId($connection, $manager, $type, $localId, $settings);
            if (($settings['createMissingReferences'] ?? false) && isset(ShopwareCatalogueDependencies::ENTITIES[$type])
                && ($id === null || $id === ShopwareCatalogueDependencies::id($connection, $type, $localId))) {
                try {
                    $id = $planner->plan($type, $localId, $connection, $manager, $settings, $dependencies);
                } catch (\DomainException $exception) {
                    $issues[] = $exception->getMessage();
                }
            }
            if ($id === null) {
                $issues[] = 'Missing '.$type.' mapping: '.$localId;
            } else {
                $references[$type][$id] = $id;
                if (in_array($type, ['tax', 'currency'], true)) {
                    $table = $type === 'tax' ? 'taxes' : 'currencies';
                    $column = $type === 'tax' ? 'rate' : 'code';
                    $expectedReferences[$type][$id] = $manager->getConnection()->fetchOne("SELECT $column FROM $table WHERE id = :id", ['id' => $localId]);
                }
            }

            return $id;
        };
        $parent = $product->getParent();
        $payload = [
            'id' => $this->productId($connection, $manager, $product, $settings),
            'productNumber' => $product->getSku(),
        ];
        if (!$product->getSku()) {
            $issues[] = 'A product number is required.';
        }
        $translations = $manager->getRepository(ProductTranslation::class)->findBy(['product' => $product]);
        $parentTranslations = [];
        if ($parent !== null) {
            foreach ($manager->getRepository(ProductTranslation::class)->findBy(['product' => $parent]) as $translation) {
                $parentTranslations[(string) $translation->getLocale()->getId()] = $translation;
            }
        }
        if ($translations === [] && $parent !== null) {
            $translations = array_values($parentTranslations);
        }
        $name = $product->getSku() ?? '';
        foreach ($translations as $translation) {
            if ($translation->getLocale()->getCode() === $connection->getTenant()->getDefaultSnippetLocale() || $name === ($product->getSku() ?? '')) {
                $name = $translation->getName() === $product->getSku()
                    ? (($parentTranslations[(string) $translation->getLocale()->getId()] ?? null)?->getName() ?? $translation->getName())
                    : $translation->getName();
            }
        }
        if ($create || in_array('content', $settings['fields'], true)) {
            if ($translations === []) {
                $issues[] = 'A product name is required.';
            }
            $payload['name'] = $name;
            $payload['ean'] = $product->getEan();
            $payload['manufacturerNumber'] = $product->getManufacturerNumber();
            foreach ($translations as $translation) {
                $languageId = $resolve('locale', (string) $translation->getLocale()->getId());
                if ($languageId !== null) {
                    $payload['translations'][$languageId] = [
                        'name' => $translation->getName() === $product->getSku()
                            ? (($parentTranslations[(string) $translation->getLocale()->getId()] ?? null)?->getName() ?? $translation->getName())
                            : $translation->getName(),
                        'description' => $translation->getDescription(),
                        'metaTitle' => $translation->getMetaTitle(),
                        'metaDescription' => $translation->getMetaDescription(),
                        'keywords' => $translation->getMetaKeywords(),
                    ];
                }
            }
        }
        if ($parent !== null) {
            if ($parent->getParent() !== null) {
                $issues[] = 'Nested variant parents are not supported by Shopware.';
            }
            $payload['parentId'] = $this->productId($connection, $manager, $parent, $settings);
            $values = $manager->getRepository(ProductVariantOptionValue::class)->findBy([
                'tenant' => $connection->getTenant(), 'product' => $product,
            ]);
            $optionIds = [];
            foreach ($values as $value) {
                $optionIds[(string) $value->getProperty()->getId()] = (string) $value->getProperty()->getId();
            }
            foreach ($product->getOptionValues() as $value) {
                if (is_string($value) && Uuid::isValid($value)) {
                    $optionIds[$value] = $value;
                } elseif ($value === '*') {
                    $issues[] = 'Wildcard variants cannot be represented safely in Shopware.';
                }
            }
            if ($optionIds === []) {
                $issues[] = 'The variant has no resolved property values.';
            }
            foreach ($optionIds as $localId) {
                $id = $resolve('property', $localId);
                if ($id !== null) {
                    $payload['options'][] = ['id' => $id];
                }
            }
        }
        if ($parent === null) {
            $optionIds = $manager->getConnection()->fetchFirstColumn(
                'SELECT DISTINCT v.property_id FROM product_variant_option_values v JOIN products child ON child.id = v.product_id WHERE child.parent_id = :id AND child.tenant_id = :tenant AND v.tenant_id = :tenant',
                ['id' => (string) $product->getId(), 'tenant' => (string) $connection->getTenant()->getId()],
            );
            foreach ($optionIds as $localId) {
                $id = $resolve('property', $localId);
                if ($id !== null) {
                    $payload['configuratorSettings'][] = [
                        'id' => str_replace('-', '', Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), $payload['id'].':configurator:'.$id)->toRfc4122()),
                        'optionId' => $id,
                    ];
                }
            }
        }
        if ($create || in_array('prices', $settings['fields'], true)) {
            $tax = $product->getTax() ?? $parent?->getTax();
            if ($tax === null) {
                $issues[] = 'A tax mapping is required.';
            } else {
                $payload['taxId'] = $resolve('tax', (string) $tax->getId());
            }
            $prices = $product->getPrice() ?: ($parent?->getPrice() ?? []);
            $payload['price'] = [];
            foreach ($prices as $price) {
                $currencyId = $price['currencyId'] ?? null;
                if (!is_string($currencyId) || !Uuid::isValid($currencyId)) {
                    $issues[] = 'The price has no system currency.';
                    continue;
                }
                $id = $resolve('currency', $currencyId);
                if ($id === null) {
                    continue;
                }
                $gross = $price['gross'] ?? null;
                $net = $price['net'] ?? null;
                if (!is_numeric($gross) || !is_numeric($net) || (float) $gross < 0 || (float) $net < 0) {
                    $issues[] = 'A valid gross and net selling price is required.';
                    continue;
                }
                $factor = bcadd('1', bcdiv($settings['priceMarkup'], '100', 8), 8);
                $payload['price'][] = [
                    'currencyId' => $id,
                    'gross' => (float) bcmul((string) $gross, $factor, 4),
                    'net' => (float) bcmul((string) $net, $factor, 4),
                    'linked' => (bool) ($price['linked'] ?? false),
                ];
                $index = array_key_last($payload['price']);
                foreach (['listPrice', 'regulationPrice'] as $key) {
                    if (isset($price[$key]) && is_numeric($price[$key]['gross'] ?? null) && is_numeric($price[$key]['net'] ?? null)) {
                        $payload['price'][$index][$key] = [
                            'gross' => (float) bcmul((string) $price[$key]['gross'], $factor, 4),
                            'net' => (float) bcmul((string) $price[$key]['net'], $factor, 4),
                            'linked' => (bool) ($price[$key]['linked'] ?? false),
                        ];
                    }
                }
            }
            if ($payload['price'] === []) {
                $issues[] = 'At least one mapped selling price is required.';
            }
        }
        if (in_array('classification', $settings['fields'], true)) {
            $manufacturer = $product->getManufacturer() ?? $parent?->getManufacturer();
            if ($manufacturer !== null) {
                $payload['manufacturerId'] = $resolve('manufacturer', (string) $manufacturer->getId());
            }
            foreach ($manager->getRepository(CategoryProduct::class)->findBy(['product' => $product]) as $assignment) {
                $id = $resolve('category', (string) $assignment->getCategory()->getId());
                if ($id !== null) {
                    $payload['categories'][] = ['id' => $id];
                }
            }
            foreach ($manager->getRepository(ProductPropertyAssignment::class)->findBy(['tenant' => $connection->getTenant(), 'product' => $product]) as $assignment) {
                $id = $resolve('property', (string) $assignment->getProperty()->getId());
                if ($id !== null) {
                    $payload['properties'][] = ['id' => $id];
                }
            }
        }
        if (in_array('fulfilment', $settings['fields'], true)) {
            foreach (['unit' => $product->getUnit(), 'deliveryTime' => $product->getDeliveryTimeReference()] as $type => $reference) {
                if ($reference !== null) {
                    $payload[$type.'Id'] = $resolve($type, (string) $reference->getId());
                }
            }
            foreach (['minPurchase' => $product->getMinPurchaseQuantity(), 'purchaseSteps' => $product->getPurchaseSteps(), 'maxPurchase' => $product->getMaxPurchaseQuantity()] as $key => $quantity) {
                if ($quantity !== null) {
                    if ((float) $quantity != floor((float) $quantity)) {
                        $issues[] = 'Shopware purchase quantities must be whole numbers.';
                    }
                    $payload[$key] = (int) $quantity;
                }
            }
            $payload['purchaseUnit'] = $product->getPurchaseUnit();
            $payload['referenceUnit'] = $product->getReferenceUnit();
            $payload['weight'] = $product->getWeightGrams() === null ? null : $product->getWeightGrams() / 1000;
            $payload['length'] = $product->getLengthMillimeters();
            $payload['width'] = $product->getWidthMillimeters();
            $payload['height'] = $product->getHeightMillimeters();
            $payload['packUnit'] = $product->getPackUnit();
            $payload['packUnitPlural'] = $product->getPackUnitPlural();
            $payload['restockTime'] = $product->getRestockTimeDays();
            $payload['releaseDate'] = $product->getReleaseDate()?->format(DATE_ATOM);
            $payload['shippingFree'] = $product->isFreeShipping();
            $payload['isCloseout'] = $product->isClearanceSale();
        }
        if (in_array('customFields', $settings['fields'], true)) {
            foreach ($translations as $translation) {
                $languageId = $resolve('locale', (string) $translation->getLocale()->getId());
                foreach ($translation->getCustomFields() as $key => $value) {
                    $localIds = $manager->getConnection()->fetchFirstColumn(
                        'SELECT id FROM custom_fields WHERE tenant_id = :tenant AND technical_name = :name ORDER BY id LIMIT 2',
                        ['tenant' => (string) $connection->getTenant()->getId(), 'name' => $key],
                    );
                    if (count($localIds) !== 1) {
                        $issues[] = 'Missing or ambiguous custom field definition: '.$key;
                        continue;
                    }
                    $id = $resolve('customField', $localIds[0]);
                    if ($id !== null && $languageId !== null) {
                        // The worker resolves validated destination IDs to technical names.
                        $payload['translations'][$languageId]['customFields'][$id] = $value;
                    }
                }
            }
        }
        $media = [];
        if (in_array('media', $settings['fields'], true)) {
            foreach ($manager->getRepository(ProductMedia::class)->findBy(['tenant' => $connection->getTenant(), 'product' => $product], ['sortOrder' => 'ASC']) as $assignment) {
                $file = $assignment->getMedia();
                try {
                    $path = (new \App\Service\TenantMediaStorage($this->projectDirectory))
                        ->path($file, $connection->getTenant());
                } catch (\DomainException) {
                    $issues[] = 'Missing local image: '.$assignment->getFileName();
                    continue;
                }
                $checksum = hash_file('sha256', $path);
                if ($file->getChecksum() !== null && $file->getChecksum() !== $checksum) {
                    $issues[] = 'The image content differs from its stored checksum. Upload it as a new image: '.$assignment->getFileName();
                    continue;
                }
                if (!$file->getFileExtension() || !$file->getMimeType()) {
                    $issues[] = 'The image extension and MIME type are required: '.$assignment->getFileName();
                    continue;
                }
                $id = $this->externalId($connection, $manager, 'media', (string) $file->getId(), $settings)
                    ?? str_replace('-', '', Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), 'connect:'.$connection->getTenant()->getId().':'.$connection->getId().':media:'.$file->getId().':'.$checksum)->toRfc4122());
                $associationId = str_replace('-', '', Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), $payload['id'].':'.$id)->toRfc4122());
                $media[] = [
                    'id' => $id,
                    'localId' => (string) $file->getId(),
                    'storageKey' => $file->getStorageKey(),
                    'extension' => $file->getFileExtension(),
                    'mimeType' => $file->getMimeType(),
                    'checksum' => $checksum,
                ];
                $payload['media'][] = [
                    'id' => $associationId,
                    'mediaId' => $id,
                    'position' => $assignment->getSortOrder(),
                ];
                $payload['coverId'] ??= $associationId;
            }
        }
        if ($create) {
            // Catalogue publication never imports/deducts warehouse stock.
            $payload['stock'] = 0;
            $payload['active'] = false;
        }
        if ($settings['publicationMode'] !== 'keep') {
            $payload['active'] = $settings['publicationMode'] === 'activate';
        }
        $payload['visibilities'] = [[
            'id' => str_replace('-', '', Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), $payload['id'].':'.$settings['salesChannelId'])->toRfc4122()),
            'salesChannelId' => $settings['salesChannelId'],
            'visibility' => $settings['publicationMode'] === 'deactivate' ? 10 : 30,
        ]];

        return [
            'name' => $name,
            'payload' => $payload,
            'media' => $media,
            'references' => $references,
            'expectedReferences' => $expectedReferences,
            'dependencies' => array_values($dependencies),
            'issues' => array_values(array_unique($issues)),
        ];
    }

    public function mediaPath(IntegrationConnection $connection, string $storageKey): string
    {
        return (new \App\Service\TenantMediaStorage($this->projectDirectory))
            ->pathForKey($connection->getTenant(), $storageKey);
    }
}
