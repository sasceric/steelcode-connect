<?php

namespace App\MessageHandler;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSalesChannel;
use App\Entity\Product;
use App\Entity\ProductChannelPublication;
use App\Integration\CatalogueChunkDeadline;
use App\Integration\CatalogueExportSettings;
use App\Integration\CatalogueExportWorkflow;
use App\Integration\ImportCancellation;
use App\Integration\ImportCancelledException;
use App\Integration\IntegrationRetryLaterException;
use App\Integration\WooCommerceCatalogueDependencies;
use App\Integration\WooCommerceCataloguePayload;
use App\Integration\WooCommerceCatalogueReferences;
use App\Integration\WooCommerceClient;
use App\Message\ExportWooCommerceCatalogue;
use App\Service\InventorySyncOutboxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ExportWooCommerceCatalogueHandler
{
    public function __construct(
        private readonly CatalogueExportWorkflow $workflow,
        private readonly EntityManagerInterface $manager,
        private readonly WooCommerceClient $client,
        private readonly WooCommerceCataloguePayload $builder,
        private readonly WooCommerceCatalogueDependencies $dependencies,
        private readonly ImportCancellation $cancellation,
        private readonly InventorySyncOutboxService $outbox,
        private readonly ?WooCommerceCatalogueReferences $references = null,
    )
    {
    }

    public function __invoke(ExportWooCommerceCatalogue $message): void
    {
        $this->workflow->handle($message, 'woocommerce', [
            'build' => $this->builder->build(...),
            'destination' => function ($connection, $settings): array {
                if ($this->references !== null) {
                    $fresh = $this->references->settings($connection, $this->manager, $settings, true);
                    if (isset($settings['_woo']) && \App\Integration\CataloguePayloadHash::of($settings['_woo']) !== \App\Integration\CataloguePayloadHash::of($fresh['_woo'])) {
                        throw new \DomainException('WooCommerce currency, tax or measurement settings changed after preview. Build a new preview.');
                    }
                }

                return ['id' => 'store', 'name' => 'WooCommerce store', 'active' => true];
            },
            'preview' => $this->previewBatch(...),
            'publish' => $this->publish(...),
        ]);
    }

    private function targets(string $url, array $secrets, array $products, IntegrationConnection $connection, array $settings): array
    {
        $skus = array_values(array_filter(array_map(static fn (Product $product): ?string => $product->getSku(), $products)));
        if ($skus === []) {
            return [];
        }
        $response = $this->client->queued('GET', $url, $secrets, 'products', query: ['sku' => implode(',', $skus), 'per_page' => 100, 'context' => 'edit']);
        if ((int) ($response['headers']['x-wp-total'][0] ?? count($response['data'])) > 100) {
            throw new \DomainException('Duplicate destination SKUs prevent safe publication.');
        }
        $targets = [];
        foreach ($response['data'] as $target) {
            $targets[(string) $target['id']] = $target;
        }
        $parents = [];
        foreach ($products as $product) {
            $parent = $product->getParent();
            if ($parent === null) {
                continue;
            }
            $id = $this->builder->externalId($connection, $this->manager, 'product', (string) $parent->getId(), $settings);
            if ($id !== null) {
                $parents[$id][] = $product->getSku();
            }
        }
        foreach ($parents as $id => $variantSkus) {
            $result = $this->client->queued('GET', $url, $secrets, 'products/'.$id.'/variations', query: ['sku' => implode(',', $variantSkus), 'per_page' => 100, 'context' => 'edit']);
            foreach ($result['data'] as $target) {
                $target['parent_id'] = (int) $id;
                $targets[(string) $target['id']] = $target;
            }
        }

        // Imported Woo records can have blank SKUs or a different local fallback
        // number. Their mapped identity is authoritative; SKU-only reads must
        // not make the final conflict check mistake them for deleted products.
        $missing = [];
        foreach ($products as $product) {
            $id = $this->builder->externalId($connection, $this->manager, 'product', (string) $product->getId(), $settings);
            if ($id !== null && !isset($targets[$id])) {
                $missing[$this->resource($product, $connection, $settings)][] = $id;
            }
        }
        foreach ($missing as $resource => $ids) {
            $result = $this->client->queued('GET', $url, $secrets, $resource, query: [
                'include' => implode(',', array_unique($ids)),
                'per_page' => 100,
                'context' => 'edit',
            ]);
            foreach ($result['data'] as $target) {
                if (preg_match('#^products/(\d+)/variations$#', $resource, $match)) {
                    $target['parent_id'] = (int) $match[1];
                }
                $targets[(string) $target['id']] = $target;
            }
        }

        return $targets;
    }

    private function previewBatch(
        ExportWooCommerceCatalogue $message,
        IntegrationConnection $connection,
        IntegrationImportRun $run,
        array $settings,
        string $url,
        array $secrets,
        array $ids,
        ?float $deadline = null,
    ): bool
    {
        $database = $this->manager->getConnection();
        $products = $this->manager->getRepository(Product::class)->findBy(['tenant' => $connection->getTenant(), 'id' => $ids]);
        $targets = $this->targets($url, $secrets, $products, $connection, $settings);
        $mode = $database->fetchOne('SELECT sync_mode FROM integration_export_plans WHERE id = :plan AND tenant_id = :tenant', ['plan' => $message->planId, 'tenant' => $message->tenantId]);
        foreach ($products as $product) {
            $this->cancellation->throwIfCancelled($run->getId());
            $id = $this->builder->externalId($connection, $this->manager, 'product', (string) $product->getId(), $settings);
            $target = $id === null ? null : ($targets[$id] ?? $this->client->queued('GET', $url, $secrets, $this->resource($product, $connection, $settings).'/'.$id, query: ['context' => 'edit'])['data']);
            $built = $this->builder->build($product, $connection, $this->manager, $settings, $target === null);
            $issues = $built['issues'];
            try {
                $this->dependencies->resolve($built, $connection, $this->manager, $settings, $url, $secrets, false, microtime(true) + 10);
                $this->assertIdentity($product, $connection, $settings, $target, $targets, $id);
                $baseline = $this->baseline($message, (string) $product->getId());
                if ($mode === 'automatic' && $baseline && $target !== null && $baseline['target_hash'] !== null
                    && self::targetHash($target, json_decode($baseline['owned_payload'], true)) !== $baseline['target_hash']) {
                    throw new \DomainException('WooCommerce-owned fields changed since the last publication. Review a manual preview before overwriting.');
                }
            } catch (CatalogueChunkDeadline) {
                return false;
            } catch (\DomainException $exception) {
                $issues[] = $exception->getMessage();
            }
            $sourceHash = \App\Integration\CataloguePayloadHash::of($built);
            $syncHash = \App\Integration\CataloguePayloadHash::of($this->builder->build($product, $connection, $this->manager, $settings, false));
            $built['syncSourceHash'] = $syncHash;
            $database->transactional(function () use ($database, $message, $product, $run, $id, $built, $issues, $sourceHash, $syncHash, $target, $connection): void {
                $hash = CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? [], 'woocommerce');
                $database->executeStatement(
                    "INSERT INTO integration_catalogue_publications (tenant_id, connection_id, product_id, settings_hash, source_hash, owned_payload, attempt_hash)
                        VALUES (:tenant, :connection, :product, :hash, :source, '{}', :attempt)
                        ON CONFLICT (tenant_id, connection_id, product_id) DO UPDATE SET attempt_hash = EXCLUDED.attempt_hash, attempted_at = CURRENT_TIMESTAMP",
                    ['tenant' => $message->tenantId, 'connection' => $message->connectionId, 'product' => (string) $product->getId(), 'hash' => $hash, 'source' => $syncHash, 'attempt' => $hash.':'.$syncHash],
                );
                $database->insert('integration_export_items', [
                    'id' => (string) Uuid::v7(), 'plan_id' => $message->planId,
                    'product_id' => (string) $product->getId(),
                    'parent_id' => $product->getParent() ? (string) $product->getParent()->getId() : null,
                    'external_id' => $id ?? 'new',
                    'name' => mb_substr($built['name'], 0, 255), 'sku' => $product->getSku() ?? '',
                    'action' => $target === null ? 'create' : 'update',
                    'payload' => json_encode($built, JSON_THROW_ON_ERROR),
                    'source_hash' => $sourceHash,
                    'target_hash' => $target === null ? null : self::targetHash($target, $this->ownedForPreview($built, $target)),
                    'issues' => json_encode(array_values(array_unique($issues)), JSON_THROW_ON_ERROR),
                ]);
                if ($issues === []) {
                    $run->recordSuccess($target === null);
                } else {
                    $run->recordFailure();
                }
                $this->manager->flush();
            });
            $this->manager->detach($product);
            $this->trim();
            if ($deadline !== null && microtime(true) >= $deadline) {
                return false;
            }
        }

        return true;
    }

    private function publish(
        ExportWooCommerceCatalogue $message,
        IntegrationConnection $connection,
        IntegrationImportRun $run,
        array $settings,
        string $url,
        array $secrets,
        array $channel,
        bool $bounded = true,
    ): void
    {
        $database = $this->manager->getConnection();
        $deadline = microtime(true) + 10;
        $rows = $database->fetchAllAssociative(
            <<<'SQL'
SELECT * FROM integration_export_items
WHERE plan_id = :plan AND status = 'pending'
  AND parent_id IS NOT DISTINCT FROM (
      SELECT parent_id FROM integration_export_items
      WHERE plan_id = :plan AND status = 'pending'
      ORDER BY parent_id NULLS FIRST, product_id LIMIT 1
  )
ORDER BY product_id LIMIT 25
SQL,
            ['plan' => $message->planId],
        );
        $products = [];
        foreach ($rows as $item) {
            $product = $this->manager->getRepository(Product::class)->findOneBy(['tenant' => $connection->getTenant(), 'id' => $item['product_id']]);
            if ($product instanceof Product) {
                $products[$item['product_id']] = $product;
            }
        }
        $targets = $this->targets($url, $secrets, array_values($products), $connection, $settings);
        $entries = [];
        $resource = null;
        foreach ($rows as $item) {
            try {
                $this->cancellation->throwIfCancelled($run->getId());
                $this->assertSettings($message, $connection, $url);
                $product = $products[$item['product_id']] ?? throw new \DomainException('The source product no longer exists.');
                $built = $this->builder->build($product, $connection, $this->manager, $settings, $item['action'] === 'create');
                if (\App\Integration\CataloguePayloadHash::of($built) !== $item['source_hash']) {
                    throw new \DomainException('Catalogue data changed after preview. Build a new preview.');
                }
                if ($item['parent_id'] !== null && $this->builder->externalId($connection, $this->manager, 'product', $item['parent_id'], $settings) === null
                    && $database->fetchOne("SELECT 1 FROM integration_export_items WHERE plan_id = :plan AND product_id = :parent AND status = 'pending'", ['plan' => $message->planId, 'parent' => $item['parent_id']])) {
                    break;
                }
                $route = $this->resource($product, $connection, $settings);
                if ($resource !== null && $route !== $resource) {
                    break; // One bounded API batch per parent-specific resource.
                }
                $resource = $route;
                $target = $item['external_id'] === 'new' ? null : ($targets[$item['external_id']] ?? $this->client->queued('GET', $url, $secrets, $route.'/'.$item['external_id'], query: ['context' => 'edit'])['data']);
                if ($target === null && $item['write_started']) {
                    foreach ($targets as $candidate) {
                        if (($candidate['sku'] ?? null) === $item['sku'] && $this->marker($candidate) === $this->identity($message, $item['product_id'])) {
                            $target = $candidate;
                        }
                    }
                }
                if (!$item['write_started']) {
                    $this->assertIdentity($product, $connection, $settings, $target, $targets, $item['external_id'] === 'new' ? null : $item['external_id']);
                }
                $payload = $this->dependencies->resolve($built, $connection, $this->manager, $settings, $url, $secrets, true, $deadline);
                $this->reuseImages($payload, $target, $built, $this->baseline($message, $item['product_id']));
                $payload['meta_data'] ??= [];
                $payload['meta_data'][] = ['key' => '_connect_catalogue_identity', 'value' => $this->identity($message, $item['product_id'])];
                $this->reuseMetaIds($payload, $target);
                if ($target !== null && $item['write_started'] && $this->marker($target) === $this->identity($message, $item['product_id'])
                    && self::targetHash($target, $payload) === self::targetHash($payload, $payload)) {
                    $this->acknowledge($message, $connection, $run, $settings, $item, $built, $payload, $target);
                    continue;
                }
                if ($item['action'] === 'create' && $target !== null) {
                    throw new \DomainException('A destination SKU exists but cannot be verified as this exact committed creation. Reconcile before retrying.');
                }
                if ($target !== null && self::targetHash($target, $this->ownedForPreview($built, $target)) !== $item['target_hash']) {
                    throw new \DomainException('Destination data changed after preview; publication stopped for this product.');
                }
                if ($target !== null) {
                    $payload['id'] = (int) $target['id'];
                }
                $entries[] = ['item' => $item, 'built' => $built, 'payload' => $payload, 'product' => $product];
            } catch (CatalogueChunkDeadline) {
                break;
            } catch (\Throwable $exception) {
                $this->failure($message, $run, $item, $exception);
            }
        }
        if ($entries === []) {
            return;
        }
        // Re-read local sources and destination snapshots immediately before writing.
        $freshTargets = $this->targets($url, $secrets, array_column($entries, 'product'), $connection, $settings);
        $valid = [];
        foreach ($entries as $entry) {
            try {
                $this->assertSettings($message, $connection, $url);
                $this->manager->refresh($entry['product']);
                $fresh = $this->builder->build($entry['product'], $connection, $this->manager, $settings, $entry['item']['action'] === 'create');
                if (\App\Integration\CataloguePayloadHash::of($fresh) !== $entry['item']['source_hash']) {
                    throw new \DomainException('Source data changed while preparing the publication.');
                }
                $target = isset($entry['payload']['id']) ? ($freshTargets[(string) $entry['payload']['id']] ?? null) : null;
                if (isset($entry['payload']['id']) && ($target === null || self::targetHash($target, $this->ownedForPreview($fresh, $target)) !== $entry['item']['target_hash'])) {
                    throw new \DomainException('Destination data changed while preparing the publication.');
                }
                $database->update('integration_export_items', ['write_started' => true], ['id' => $entry['item']['id'], 'plan_id' => $message->planId]);
                $valid[] = $entry;
            } catch (\Throwable $exception) {
                $this->failure($message, $run, $entry['item'], $exception);
            }
        }
        if ($valid === []) {
            return;
        }
        $request = ['create' => [], 'update' => []];
        foreach ($valid as $entry) {
            $request[isset($entry['payload']['id']) ? 'update' : 'create'][] = $entry['payload'];
        }
        $response = $this->client->queued('POST', $url, $secrets, $resource.'/batch', $request)['data'];
        foreach (['create', 'update'] as $action) {
            $group = array_values(array_filter($valid, static fn (array $entry): bool => isset($entry['payload']['id']) === ($action === 'update')));
            foreach ($group as $index => $entry) {
                try {
                    $result = $response[$action][$index] ?? null;
                    if (!is_array($result) || isset($result['error']) || empty($result['id'])) {
                        throw new \DomainException('WooCommerce rejected this '.$action.': '.($result['error']['message'] ?? 'missing result'));
                    }
                    $this->acknowledge($message, $connection, $run, $settings, $entry['item'], $entry['built'], $entry['payload'], $result);
                } catch (\Throwable $exception) {
                    $this->failure($message, $run, $entry['item'], $exception);
                }
            }
        }
        $this->trim();
    }

    private function resource(Product $product, IntegrationConnection $connection, array $settings): string
    {
        $parent = $product->getParent();
        if ($parent === null) {
            return 'products';
        }
        $id = $this->builder->externalId($connection, $this->manager, 'product', (string) $parent->getId(), $settings);
        if ($id === null) {
            throw new \DomainException('Publish the variation parent before its variations.');
        }

        return 'products/'.$id.'/variations';
    }

    private function assertIdentity(Product $product, IntegrationConnection $connection, array $settings, ?array $target, array $targets, ?string $id): void
    {
        foreach ($targets as $candidate) {
            if (($candidate['sku'] ?? null) === $product->getSku() && (string) $candidate['id'] !== $id) {
                throw new \DomainException('This SKU already exists in WooCommerce. Map it explicitly; it will not be adopted by name or SKU alone.');
            }
        }
        if ($id !== null) {
            $owner = $this->manager->getConnection()->fetchOne(
                "SELECT local_id FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = 'product' AND external_id = :id",
                ['tenant' => (string) $connection->getTenant()->getId(), 'connection' => (string) $connection->getId(), 'id' => $id],
            );
            if ($owner && $owner !== (string) $product->getId()) {
                throw new \DomainException('The destination identity belongs to another local product.');
            }
        }
        if ($target !== null) {
            $parent = $product->getParent();
            $parentId = $parent ? $this->builder->externalId($connection, $this->manager, 'product', (string) $parent->getId(), $settings) : null;
            if ((int) ($target['parent_id'] ?? 0) !== (int) $parentId) {
                throw new \DomainException('The existing WooCommerce product belongs to a different parent.');
            }
        }
    }

    private function acknowledge(ExportWooCommerceCatalogue $message, IntegrationConnection $connection, IntegrationImportRun $run, array $settings, array $item, array $built, array $payload, array $target): void
    {
        if (($target['sku'] ?? null) !== $item['sku']) {
            throw new \DomainException('WooCommerce returned a different product identity.');
        }
        $database = $this->manager->getConnection();
        $database->transactional(function () use ($database, $message, $connection, $run, $settings, $item, $built, &$payload, $target): void {
            $product = $this->manager->getRepository(Product::class)->findOneBy(['tenant' => $connection->getTenant(), 'id' => $item['product_id']]);
            if (!$product instanceof Product) {
                throw new \DomainException('The source product no longer exists.');
            }
            $external = (string) $target['id'];
            $owner = $database->fetchOne("SELECT local_id FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = 'product' AND external_id = :external", ['tenant' => $message->tenantId, 'connection' => $message->connectionId, 'external' => $external]);
            if ($owner && $owner !== $item['product_id']) {
                throw new \DomainException('The published identity belongs to another product.');
            }
            $database->executeStatement(
                "INSERT INTO integration_entity_mappings (id, tenant_id, connection_id, entity_type, external_id, local_id) VALUES (:id, :tenant, :connection, 'product', :external, :local) ON CONFLICT (connection_id, entity_type, external_id) DO NOTHING",
                ['id' => (string) Uuid::v7(), 'tenant' => $message->tenantId, 'connection' => $message->connectionId, 'external' => $external, 'local' => $item['product_id']],
            );
            $channel = $this->manager->getRepository(IntegrationSalesChannel::class)->findOneBy(['tenant' => $connection->getTenant(), 'connection' => $connection, 'externalId' => 'store']);
            if (!$channel instanceof IntegrationSalesChannel) {
                $channel = new IntegrationSalesChannel($connection->getTenant(), $connection, 'store', 'WooCommerce store', 'storefront', true, []);
                $this->manager->persist($channel);
            }
            $publication = $this->manager->getRepository(ProductChannelPublication::class)->findOneBy(['tenant' => $connection->getTenant(), 'product' => $product, 'salesChannel' => $channel]);
            $visibility = ($target['status'] ?? '') === 'publish' ? 30 : 0;
            if (!$publication instanceof ProductChannelPublication) {
                $this->manager->persist(new ProductChannelPublication(
                    $connection->getTenant(), $product, $channel, $visibility, $external,
                ));
            } else {
                $publication->update($visibility, $external);
            }
            $this->captureImages($payload, $built, $target);
            $database->executeStatement(
                'UPDATE integration_catalogue_publications SET source_hash = :source, settings_hash = :hash, target_hash = :target, owned_payload = :payload, published_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant AND connection_id = :connection AND product_id = :product',
                ['source' => $built['syncSourceHash'] ?? \App\Integration\CataloguePayloadHash::of($this->builder->build($product, $connection, $this->manager, $settings, false)),
                    'hash' => CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? [], 'woocommerce'),
                    'target' => self::targetHash($target, $payload), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'tenant' => $message->tenantId, 'connection' => $message->connectionId, 'product' => $item['product_id']],
            );
            if (($connection->getConfiguration()['importSettings']['stockAuthority'] ?? '') === 'connect' && empty($target['variations'])) {
                $this->outbox->queue($connection->getTenant(), $product, $this->manager);
            }
            $database->update('integration_export_items', ['status' => 'published', 'external_id' => $external, 'result' => null], ['id' => $item['id'], 'plan_id' => $message->planId]);
            $run->recordSuccess($item['action'] === 'create');
            $this->manager->flush();
        });
    }

    private function baseline(ExportWooCommerceCatalogue $message, string $product): array|false
    {
        return $this->manager->getConnection()->fetchAssociative('SELECT * FROM integration_catalogue_publications WHERE tenant_id = :tenant AND connection_id = :connection AND product_id = :product', ['tenant' => $message->tenantId, 'connection' => $message->connectionId, 'product' => $product]);
    }

    private function identity(ExportWooCommerceCatalogue $message, string $product): string
    {
        return hash('sha256', $message->tenantId.':'.$message->connectionId.':'.$product);
    }

    private function marker(array $target): ?string
    {
        foreach ($target['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? '') === '_connect_catalogue_identity') {
                return is_string($meta['value']) ? $meta['value'] : null;
            }
        }

        return null;
    }

    private function reuseMetaIds(array &$payload, ?array $target): void
    {
        foreach ($payload['meta_data'] ?? [] as $index => $meta) {
            foreach ($target['meta_data'] ?? [] as $existing) {
                if ($existing['key'] === $meta['key'] && isset($existing['id'])) {
                    $payload['meta_data'][$index]['id'] = $existing['id'];
                    break;
                }
            }
        }
    }

    private function ownedForPreview(array $built, array $target): array
    {
        // Compare exactly the fields selected, using their destination shape.
        $owned = [];
        foreach (array_keys($built['payload']) as $key) {
            $owned[$key] = $built['payload'][$key];
            if (in_array($key, ['images', 'image', 'attributes', 'default_attributes', 'categories', 'brands', 'tags'], true)) {
                $owned[$key] = $target[$key] ?? [];
            }
        }

        return $owned;
    }

    private function reuseImages(array &$payload, ?array $target, array $built, array|false $baseline): void
    {
        $map = $baseline ? (json_decode($baseline['owned_payload'], true)['__media'] ?? []) : [];
        $canonical = $built['payload']['images'] ?? (isset($built['payload']['image']) ? [$built['payload']['image']] : []);
        $current = $target['images'] ?? (isset($target['image']) && is_array($target['image']) ? [$target['image']] : []);
        foreach ($canonical as $index => $image) {
            $token = $image['id'] ?? '';
            $known = $map[$token] ?? null;
            if (!$known || !in_array($known['id'], array_column($current, 'id'), true)) {
                continue;
            }
            $dependency = array_values(array_filter($built['dependencies'], static fn (array $item): bool => WooCommerceCataloguePayload::token($item['type'], $item['localId']) === $token))[0] ?? null;
            if ($dependency === null || $dependency['payload']['checksum'] !== $known['checksum']) {
                continue;
            }
            $value = ['id' => $known['id'], 'name' => $image['name'], 'alt' => $image['alt']];
            if (isset($payload['images'])) {
                $payload['images'][$index] = $value;
            } else {
                $payload['image'] = $value;
            }
        }
    }

    private function captureImages(array &$payload, array $built, array $target): void
    {
        $canonical = $built['payload']['images'] ?? (isset($built['payload']['image']) ? [$built['payload']['image']] : []);
        $current = $target['images'] ?? (isset($target['image']) && is_array($target['image']) ? [$target['image']] : []);
        foreach ($canonical as $index => $image) {
            if (empty($current[$index]['id']) || !is_string($image['id'] ?? null)) {
                continue;
            }
            $dependency = array_values(array_filter($built['dependencies'], static fn (array $item): bool => WooCommerceCataloguePayload::token($item['type'], $item['localId']) === $image['id']))[0] ?? null;
            $payload['__media'][$image['id']] = ['id' => $current[$index]['id'], 'checksum' => $dependency['payload']['checksum'] ?? null];
            $value = ['id' => $current[$index]['id'], 'name' => $image['name'], 'alt' => $image['alt']];
            if (isset($payload['images'])) {
                $payload['images'][$index] = $value;
            } else {
                $payload['image'] = $value;
            }
        }
    }

    public static function targetHash(array $target, array $payload): string
    {
        $owned = [];
        foreach ($payload as $key => $value) {
            if (in_array($key, ['id', '__media'], true)) {
                continue;
            }
            $actual = $target[$key] ?? null;
            if ($key === 'meta_data') {
                $owned[$key] = [];
                foreach ($value as $meta) {
                    $found = array_values(array_filter($target[$key] ?? [], static fn (array $entry): bool => $entry['key'] === $meta['key']));
                    $owned[$key][] = ['key' => $meta['key'], 'value' => $found[0]['value'] ?? null];
                }
            } elseif (in_array($key, ['regular_price', 'sale_price', 'weight'], true)) {
                $owned[$key] = $actual === '' || $actual === null ? '' : (is_numeric($actual) ? bcadd((string) $actual, '0', 4) : $actual);
            } elseif ($key === 'dimensions') {
                foreach (['length', 'width', 'height'] as $dimension) {
                    $item = $actual[$dimension] ?? '';
                    $owned[$key][$dimension] = $item === '' ? '' : bcadd((string) $item, '0', 4);
                }
            } elseif (in_array($key, ['categories', 'brands', 'tags'], true)) {
                $owned[$key] = array_column(is_array($actual) ? $actual : [], 'id');
            } elseif (in_array($key, ['attributes', 'default_attributes'], true)) {
                $owned[$key] = [];
                foreach ($actual ?? [] as $attribute) {
                    $entry = ['id' => (int) ($attribute['id'] ?? 0)];
                    if ($entry['id'] === 0) {
                        $entry['name'] = $attribute['name'] ?? '';
                    }
                    foreach (['variation', 'visible', 'options', 'option', 'position'] as $field) {
                        if (array_key_exists($field, $attribute)) {
                            $entry[$field] = $attribute[$field];
                        }
                    }
                    $owned[$key][] = $entry;
                }
            } elseif (in_array($key, ['image', 'images'], true)) {
                $values = $key === 'image' ? (is_array($actual) ? [$actual] : []) : ($actual ?? []);
                $intended = $key === 'image' ? [$value] : $value;
                $owned[$key] = [];
                foreach ($values as $index => $image) {
                    $entry = ['position' => $index, 'name' => $image['name'] ?? '', 'alt' => $image['alt'] ?? ''];
                    if (!empty($intended[$index]['id'])) {
                        $entry['id'] = (int) ($image['id'] ?? 0);
                    }
                    $owned[$key][] = $entry;
                }
            } else {
                $owned[$key] = $actual;
            }
        }

        return \App\Integration\CataloguePayloadHash::of($owned);
    }

    private function assertSettings(ExportWooCommerceCatalogue $message, IntegrationConnection $connection, string $url): void
    {
        $row = $this->manager->getConnection()->fetchAssociative('SELECT enabled, status, configuration, directions FROM integration_connections WHERE tenant_id = :tenant AND id = :connection', ['tenant' => $message->tenantId, 'connection' => $message->connectionId]);
        $configuration = $row ? json_decode($row['configuration'], true) : [];
        if (!$row || !$row['enabled'] || $row['status'] !== 'active' || !in_array('channel', json_decode($row['directions'], true), true)
            || ($configuration['baseUrl'] ?? '') !== $url
            || CatalogueExportSettings::hash($configuration['exportSettings'] ?? [], 'woocommerce') !== CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? [], 'woocommerce')) {
            throw new \DomainException('The connection or saved export settings changed.');
        }
    }

    private function failure(ExportWooCommerceCatalogue $message, IntegrationImportRun $run, array $item, \Throwable $exception): void
    {
        if ($exception instanceof IntegrationRetryLaterException || $exception instanceof ImportCancelledException || $exception instanceof \Doctrine\DBAL\Exception || !$this->manager->isOpen()) {
            throw $exception;
        }
        $database = $this->manager->getConnection();
        $database->transactional(function () use ($database, $message, $run, $item, $exception): void {
            $reason = preg_replace('#https?://[^\\s]+#', '[remote URL]', $exception->getMessage());
            if ($database->executeStatement("UPDATE integration_export_items SET status = 'failed', result = :reason WHERE id = :id AND plan_id = :plan AND status = 'pending'", ['reason' => mb_substr($reason, 0, 2000), 'id' => $item['id'], 'plan' => $message->planId])) {
                $run->recordFailure();
                $this->manager->flush();
            }
        });
    }

    private function trim(): void
    {
        foreach ($this->manager->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (!$entity instanceof IntegrationConnection && !$entity instanceof IntegrationImportRun
                    && !$entity instanceof \App\Entity\Tenant && !$entity instanceof IntegrationSalesChannel) {
                    $this->manager->detach($entity);
                }
            }
        }
    }
}
