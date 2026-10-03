<?php

namespace App\Service;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\Product;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSelection;
use App\Integration\CatalogueExportSettings;
use App\Integration\ShopwareCataloguePayload;
use App\Integration\WooCommerceCataloguePayload;
use App\Message\ExportWooCommerceCatalogue;
use App\Message\ExportShopwareCatalogue;
use App\Integration\CataloguePayloadHash;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;

class CatalogueSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly CatalogueExportSelection $selection,
        private readonly CatalogueExportReferences $references,
        private readonly ShopwareCataloguePayload $builder,
        private readonly MessageBusInterface $bus,
        private readonly ?WooCommerceCataloguePayload $wooBuilder = null,
    )
    {
    }

    /** Called only after owner confirmation, or from the tenant-scoped scheduler scan. */
    public function queue(IntegrationConnection $connection, ?array $productIds = null): array
    {
        if (!in_array($connection->getConnectorKey(), ['shopware', 'woocommerce'], true) || !$connection->isEnabled()
            || $connection->getStatus() !== 'active' || !in_array('channel', $connection->getDirections(), true)) {
            throw new \DomainException('Catalogue synchronization requires an active supported channel connection.');
        }
        $settings = CatalogueExportSettings::normalize($connection->getConfiguration()['exportSettings'] ?? [], $connection->getConnectorKey());
        if ($connection->getConnectorKey() === 'shopware' && $settings['salesChannelId'] === '') {
            throw new \DomainException('Choose a destination sales channel first.');
        }
        $this->references->validateLocalSettings($connection, $this->manager, $settings);
        $database = $this->manager->getConnection();
        $database->beginTransaction();
        try {
            $database->executeQuery(
                'SELECT id FROM integration_connections WHERE id = :id AND tenant_id = :tenant FOR UPDATE',
                ['id' => (string) $connection->getId(), 'tenant' => (string) $connection->getTenant()->getId()],
            );
            if ($this->active($connection)) {
                throw new \DomainException('Wait for the active integration run or cancel it first.');
            }
            $run = new IntegrationImportRun($connection->getTenant(), $connection, 'export_sync');
            $this->manager->persist($run);
            $this->manager->flush();
            $planId = (string) Uuid::v7();
            $database->insert('integration_export_plans', [
                'id' => $planId,
                'tenant_id' => (string) $connection->getTenant()->getId(),
                'connection_id' => (string) $connection->getId(),
                'run_id' => (string) $run->getId(),
                'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                'settings_hash' => CatalogueExportSettings::hash($settings, $connection->getConnectorKey()),
                'sync_mode' => $productIds === null ? 'manual' : 'automatic',
                'product_ids' => $productIds === null ? null : json_encode(array_values($productIds), JSON_THROW_ON_ERROR),
            ]);
            $class = $connection->getConnectorKey() === 'woocommerce' ? ExportWooCommerceCatalogue::class : ExportShopwareCatalogue::class;
            $this->bus->dispatch(new $class(
                (string) $connection->getTenant()->getId(),
                (string) $connection->getId(),
                $planId,
                (string) $run->getId(),
                true,
            ), $productIds === null ? [] : [new TransportNamesStamp(['catalogue'])]);
            $database->commit();

            return ['planId' => $planId, 'runId' => (string) $run->getId()];
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }
    }

    public function scan(string $tenantId, string $connectionId): void
    {
        if (!Uuid::isValid($tenantId) || !Uuid::isValid($connectionId)) {
            return;
        }
        $database = $this->manager->getConnection();
        $key = 'catalogue-scan:'.$tenantId.':'.$connectionId;
        if (!$database->fetchOne('SELECT pg_try_advisory_lock(hashtextextended(:key, 0))', ['key' => $key])) {
            return;
        }
        try {
            $connection = $this->manager->getRepository(IntegrationConnection::class)->findOneBy([
                'id' => Uuid::fromString($connectionId), 'tenant' => Uuid::fromString($tenantId),
            ]);
            if (!$connection instanceof IntegrationConnection || !in_array($connection->getConnectorKey(), ['shopware', 'woocommerce'], true)
                || !$connection->isEnabled() || $connection->getStatus() !== 'active'
                || !in_array('channel', $connection->getDirections(), true) || $this->active($connection)) {
                return;
            }
            $settings = CatalogueExportSettings::normalize($connection->getConfiguration()['exportSettings'] ?? [], $connection->getConnectorKey());
            if (!$settings['automaticSync'] || ($connection->getConnectorKey() === 'shopware' && $settings['salesChannelId'] === '')) {
                return;
            }
            $hash = CatalogueExportSettings::hash($settings, $connection->getConnectorKey());
            $state = $database->fetchAssociative(
                "SELECT *, scanned_at > CURRENT_TIMESTAMP - INTERVAL '50 seconds' AS recent FROM integration_catalogue_sync_state WHERE tenant_id = :tenant AND connection_id = :connection",
                ['tenant' => $tenantId, 'connection' => $connectionId],
            );
            if ($state && $state['settings_hash'] === $hash && $state['recent']
                && !$database->fetchOne('SELECT 1 FROM integration_catalogue_dirty WHERE tenant_id = :tenant AND connection_id = :connection LIMIT 1', [
                    'tenant' => $tenantId, 'connection' => $connectionId,
                ])) {
                return;
            }
            $cursor = $state && $state['settings_hash'] === $hash ? $state['cursor_id'] : null;
            $dirtyRows = $database->fetchAllAssociative(
                "SELECT product_id, revision FROM integration_catalogue_dirty WHERE tenant_id = :tenant AND connection_id = :connection
                    AND (changed_at <= CURRENT_TIMESTAMP - INTERVAL '5 seconds' OR first_changed_at <= CURRENT_TIMESTAMP - INTERVAL '30 seconds')
                    ORDER BY first_changed_at, product_id LIMIT 100",
                ['tenant' => $tenantId, 'connection' => $connectionId],
            );
            if ($dirtyRows === [] && $database->fetchOne(
                'SELECT 1 FROM integration_catalogue_dirty WHERE tenant_id = :tenant AND connection_id = :connection LIMIT 1',
                ['tenant' => $tenantId, 'connection' => $connectionId],
            )) {
                return; // Do not let reconciliation bypass the debounce window.
            }
            $dirtyIds = array_column($dirtyRows, 'product_id');
            $ids = $dirtyRows !== []
                ? $this->selection->matchingIds($database, $connection, $settings, $dirtyIds)
                : $this->selection->page($database, $connection, $settings, $cursor);
            $effective = $ids === [] ? $settings : $this->references->standardMappings($connection, $this->manager, $settings);
            $products = [];
            foreach ($this->manager->getRepository(Product::class)->findBy(['tenant' => $connection->getTenant(), 'id' => $ids]) as $product) {
                $products[(string) $product->getId()] = $product;
            }
            $baselines = [];
            if ($ids !== []) {
                foreach ($database->fetchAllAssociative(
                    "SELECT *, attempted_at > CURRENT_TIMESTAMP - INTERVAL '15 minutes' AS recent FROM integration_catalogue_publications
                        WHERE tenant_id = :tenant AND connection_id = :connection AND product_id IN (:ids)",
                    ['tenant' => $tenantId, 'connection' => $connectionId, 'ids' => $ids],
                    ['ids' => ArrayParameterType::STRING],
                ) as $baseline) {
                    $baselines[$baseline['product_id']] = $baseline;
                }
            }
            $changed = [];
            $lastId = null;
            $processed = array_diff($dirtyIds, $ids);
            foreach ($ids as $id) {
                $lastId = $id;
                $processed[] = $id;
                $product = $products[$id] ?? null;
                if (!$product instanceof Product) {
                    continue;
                }
                $source = CataloguePayloadHash::of(($connection->getConnectorKey() === 'woocommerce' ? $this->wooBuilder : $this->builder)->build($product, $connection, $this->manager, $effective, false));
                $baseline = $baselines[$id] ?? null;
                $same = $baseline && $baseline['settings_hash'] === $hash && $baseline['source_hash'] === $source && $baseline['published_at'] !== null;
                $backoff = $baseline && $baseline['attempt_hash'] === $hash.':'.$source && $baseline['recent'];
                if (!$same && !$backoff) {
                    $changed[] = $id;
                }
                foreach ($this->manager->getUnitOfWork()->getIdentityMap() as $entities) {
                    foreach ($entities as $entity) {
                        if (!$entity instanceof IntegrationConnection && !$entity instanceof \App\Entity\Tenant
                            && !$entity instanceof Product) {
                            $this->manager->detach($entity);
                        }
                    }
                }
                $this->manager->detach($product);
                if (count($changed) === 25) {
                    break;
                }
            }
            if ($changed !== []) {
                $this->queue($connection, $changed);
            }
            foreach ($dirtyRows as $dirty) {
                if (in_array($dirty['product_id'], $processed, true)) {
                    $database->delete('integration_catalogue_dirty', [
                        'tenant_id' => $tenantId, 'connection_id' => $connectionId,
                        'product_id' => $dirty['product_id'], 'revision' => $dirty['revision'],
                    ]);
                }
            }
            if ($dirtyRows === []) {
                $cursor = count($ids) < 100 && $lastId === ($ids === [] ? null : end($ids)) ? null : $lastId;
            }
            $database->executeStatement(<<<'SQL'
INSERT INTO integration_catalogue_sync_state (tenant_id, connection_id, settings_hash, cursor_id, scanned_at)
VALUES (:tenant, :connection, :hash, :cursor, CURRENT_TIMESTAMP)
ON CONFLICT (connection_id) DO UPDATE SET settings_hash = EXCLUDED.settings_hash,
    cursor_id = EXCLUDED.cursor_id, scanned_at = EXCLUDED.scanned_at
WHERE integration_catalogue_sync_state.tenant_id = EXCLUDED.tenant_id
SQL, ['tenant' => $tenantId, 'connection' => $connectionId, 'hash' => $hash, 'cursor' => $cursor]);
        } finally {
            $database->executeQuery('SELECT pg_advisory_unlock(hashtextextended(:key, 0))', ['key' => $key]);
            $this->manager->clear();
        }
    }

    private function active(IntegrationConnection $connection): bool
    {
        return (bool) $this->manager->getConnection()->fetchOne(
            "SELECT 1 FROM integration_import_runs WHERE tenant_id = :tenant AND connection_id = :connection AND status IN ('queued', 'running') LIMIT 1",
            ['tenant' => (string) $connection->getTenant()->getId(), 'connection' => (string) $connection->getId()],
        );
    }
}
