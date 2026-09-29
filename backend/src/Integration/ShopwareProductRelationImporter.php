<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSecret;
use App\Entity\Locale;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductCrossSelling;
use App\Entity\ProductCrossSellingAssignment;
use App\Entity\ProductCrossSellingTranslation;
use App\Entity\ProductDownload;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

final class ShopwareProductRelationImporter
{
    /** @var null|\Closure */
    private ?\Closure $ensureActive = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretCipher $cipher,
        private readonly ShopwareClient $shopwareClient,
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
    ): void {
        $this->ensureActive = \Closure::fromCallable($ensureActive);
        $connection = $run->getConnection();
        $tenant = $run->getTenant();
        $baseUrl = $connection->getConfiguration()['baseUrl'] ?? null;
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            throw new \RuntimeException('The Shopware platform URL is not configured.');
        }

        try {
            $secrets = $this->secrets($connection);
            $areas = $this->areas($connection);
            $onStage('productRelations');
            if ($areas['productDownloads']) {
                $this->syncDownloads($tenant, $connection, $baseUrl, $secrets);
            }
            if ($areas['crossSellings']) {
                $this->syncCrossSellings($tenant, $connection, $baseUrl, $secrets);
                $this->syncCrossSellingTranslations(
                    $tenant,
                    $connection,
                    $baseUrl,
                    $secrets,
                );
            }
            $this->entityManager->flush();
        } finally {
            $this->ensureActive = null;
        }
    }

    /** @param array<string, string> $secrets */
    private function syncDownloads(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
    ): void {
        $seen = [];
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'product-download',
            function (int $total, array $items) use ($tenant, $connection, &$seen): void {
                foreach ($items as $source) {
                    $this->checkActive();
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $productId = $this->string($attributes['productId'] ?? null);
                    $mediaId = $this->string($attributes['mediaId'] ?? null);
                    if ($externalId === null || $productId === null || $mediaId === null) {
                        continue;
                    }

                    $product = $this->mappedEntity(
                        $connection,
                        'product',
                        $productId,
                        Product::class,
                        $tenant,
                    );
                    $media = $this->mappedEntity(
                        $connection,
                        'media',
                        $mediaId,
                        Media::class,
                        $tenant,
                    );
                    if (!$product instanceof Product || !$media instanceof Media) {
                        continue;
                    }

                    $download = $this->mappedEntity(
                        $connection,
                        'product_download',
                        $externalId,
                        ProductDownload::class,
                        $tenant,
                    );
                    $translated = is_array($attributes['translated'] ?? null)
                        ? $attributes['translated']
                        : [];
                    $title = $this->string(
                        $translated['name'] ?? $attributes['name'] ?? null,
                    );
                    $position = max(0, (int) ($attributes['position'] ?? 0));
                    if (!$download instanceof ProductDownload) {
                        $download = new ProductDownload(
                            $tenant,
                            $product,
                            $media,
                            $title,
                            $position,
                        );
                        $this->entityManager->persist($download);
                    } else {
                        $download->update($media, $title, $position);
                    }
                    $this->ensureMapping(
                        $tenant,
                        $connection,
                        'product_download',
                        $externalId,
                        $download,
                    );
                    $seen[$externalId] = true;
                }
            },
        );

        $this->removeMissingMappedEntities(
            $connection,
            'product_download',
            ProductDownload::class,
            $seen,
        );
    }

    /** @param array<string, string> $secrets */
    private function syncCrossSellings(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
    ): void {
        $seen = [];
        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'product-cross-selling',
            function (int $total, array $items) use ($tenant, $connection, &$seen): void {
                foreach ($items as $source) {
                    $this->checkActive();
                    $attributes = $this->attributes($source);
                    $externalId = $this->externalId($source, $attributes);
                    $sourceProductId = $this->string($attributes['productId'] ?? null);
                    if ($externalId === null || $sourceProductId === null) {
                        continue;
                    }

                    $product = $this->mappedEntity(
                        $connection,
                        'product',
                        $sourceProductId,
                        Product::class,
                        $tenant,
                    );
                    if (!$product instanceof Product) {
                        continue;
                    }

                    $crossSelling = $this->mappedEntity(
                        $connection,
                        'product_cross_selling',
                        $externalId,
                        ProductCrossSelling::class,
                        $tenant,
                    );
                    $translated = is_array($attributes['translated'] ?? null)
                        ? $attributes['translated']
                        : [];
                    $name = $this->string(
                        $translated['name'] ?? $attributes['name'] ?? null,
                    ) ?? 'Cross-selling';
                    $type = $this->string($attributes['type'] ?? null) ?? 'productList';
                    $active = (bool) ($attributes['active'] ?? true);
                    $position = max(0, (int) ($attributes['position'] ?? 0));
                    $productStreamId = $this->string($attributes['productStreamId'] ?? null);
                    if (!$crossSelling instanceof ProductCrossSelling) {
                        $crossSelling = new ProductCrossSelling(
                            $tenant,
                            $product,
                            $externalId,
                            $name,
                            $type,
                            $active,
                            $position,
                            $productStreamId,
                        );
                        $this->entityManager->persist($crossSelling);
                    } else {
                        $crossSelling->update(
                            $name,
                            $type,
                            $active,
                            $position,
                            $productStreamId,
                        );
                    }
                    $this->ensureMapping(
                        $tenant,
                        $connection,
                        'product_cross_selling',
                        $externalId,
                        $crossSelling,
                    );
                    $this->syncCrossSellingAssignments(
                        $crossSelling,
                        $connection,
                        $tenant,
                        $source,
                        $attributes,
                    );
                    $seen[$externalId] = true;
                }
            },
            ['assignedProducts' => []],
        );

        $this->removeMissingMappedEntities(
            $connection,
            'product_cross_selling',
            ProductCrossSelling::class,
            $seen,
        );
    }

    /** @param array<string, string> $secrets */
    private function syncCrossSellingTranslations(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
    ): void {
        foreach ($this->translationLocales($tenant, $baseUrl, $secrets) as $languageId => $locale) {
            $this->checkActive();
            $this->shopwareClient->forEachEntityPage(
                $baseUrl,
                $secrets,
                'product-cross-selling',
                function (int $total, array $items) use ($tenant, $connection, $locale): void {
                    foreach ($items as $source) {
                        $this->checkActive();
                        $attributes = $this->attributes($source);
                        $externalId = $this->externalId($source, $attributes);
                        $translated = is_array($attributes['translated'] ?? null)
                            ? $attributes['translated']
                            : [];
                        $name = $this->string(
                            $translated['name'] ?? $attributes['name'] ?? null,
                        );
                        if ($externalId === null || $name === null) {
                            continue;
                        }

                        $crossSelling = $this->mappedEntity(
                            $connection,
                            'product_cross_selling',
                            $externalId,
                            ProductCrossSelling::class,
                            $tenant,
                        );
                        if (!$crossSelling instanceof ProductCrossSelling) {
                            continue;
                        }

                        $translation = $this->entityManager
                            ->getRepository(ProductCrossSellingTranslation::class)
                            ->findOneBy([
                                'crossSelling' => $crossSelling,
                                'locale' => $locale,
                            ]);
                        $translation ??= new ProductCrossSellingTranslation(
                            $crossSelling,
                            $locale,
                            $name,
                        );
                        $translation->update($name);
                        $this->entityManager->persist($translation);
                    }
                },
                languageId: $languageId,
            );
        }
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $attributes */
    private function syncCrossSellingAssignments(
        ProductCrossSelling $crossSelling,
        IntegrationConnection $connection,
        Tenant $tenant,
        array $source,
        array $attributes,
    ): void {
        $sourceProductIds = $this->sourceIdentifiers(
            $source,
            $attributes,
            'assignedProductIds',
            'assignedProducts',
        );
        if ($sourceProductIds === null) {
            return;
        }

        $products = [];
        foreach ($sourceProductIds as $position => $externalId) {
            $product = $this->mappedEntity(
                $connection,
                'product',
                $externalId,
                Product::class,
                $tenant,
            );
            if ($product instanceof Product) {
                $products[$product->getId()->toRfc4122()] = [
                    'product' => $product,
                    'position' => $position,
                ];
            }
        }

        $existingAssignments = $this->entityManager
            ->getRepository(ProductCrossSellingAssignment::class)
            ->findBy(['crossSelling' => $crossSelling]);
        $existingByProductId = [];
        foreach ($existingAssignments as $assignment) {
            $existingByProductId[
                $assignment->getAssignedProduct()->getId()->toRfc4122()
            ] = $assignment;
        }
        foreach ($existingByProductId as $productId => $assignment) {
            if (!isset($products[$productId])) {
                $this->entityManager->remove($assignment);
            }
        }
        foreach ($products as $productId => $sourceProduct) {
            $assignment = $existingByProductId[$productId] ?? null;
            if ($assignment instanceof ProductCrossSellingAssignment) {
                $assignment->update($sourceProduct['position']);

                continue;
            }
            $this->entityManager->persist(new ProductCrossSellingAssignment(
                $crossSelling,
                $sourceProduct['product'],
                $sourceProduct['position'],
            ));
        }
    }

    /**
     * @param array<string, true> $seen
     */
    private function removeMissingMappedEntities(
        IntegrationConnection $connection,
        string $entityType,
        string $class,
        array $seen,
    ): void {
        foreach ($this->entityManager->getRepository(IntegrationEntityMapping::class)
            ->findBy(['connection' => $connection, 'entityType' => $entityType]) as $mapping) {
            if (!$mapping instanceof IntegrationEntityMapping || isset($seen[$mapping->getExternalId()])) {
                continue;
            }

            $entity = $this->entityManager->find($class, $mapping->getLocalId());
            if (is_object($entity)) {
                $this->entityManager->remove($entity);
            }
        }
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

    /** @param array<string, mixed> $source */
    private function attributes(array $source): array
    {
        return is_array($source['attributes'] ?? null)
            ? $source['attributes']
            : $source;
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $attributes */
    private function externalId(array $source, array $attributes): ?string
    {
        return $this->string($source['id'] ?? $attributes['id'] ?? null);
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $attributes */
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

        $relation = $relationships[$relationship] ?? null;

        return is_array($relation) ? $this->identifiers($relation['data'] ?? null) : [];
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

    /**
     * @param array<string, string> $secrets
     *
     * @return array<string, Locale> Shopware language ID => local locale
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
                $translationLocales[$languageId] = $locale;
            }
        }

        return $translationLocales;
    }

    private function localeKey(string $code): string
    {
        return strtolower(str_replace('_', '-', trim($code)));
    }

    private function checkActive(): void
    {
        if ($this->ensureActive !== null) {
            ($this->ensureActive)();
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
            'productDownloads' => true,
            'crossSellings' => true,
        ];
        foreach ($defaults as $key => $default) {
            $defaults[$key] = is_bool($configuredAreas[$key] ?? null)
                ? $configuredAreas[$key]
                : $default;
        }

        return $defaults;
    }
}
