<?php

namespace App\MessageHandler;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSalesChannel;
use App\Entity\Product;
use App\Entity\ProductChannelPublication;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSelection;
use App\Integration\CatalogueExportSettings;
use App\Integration\ImportCancellation;
use App\Integration\ImportCancelledException;
use App\Integration\IntegrationImportLogger;
use App\Integration\IntegrationRetryLaterException;
use App\Integration\ShopwareCataloguePayload;
use App\Integration\ShopwareCatalogueDependencies;
use App\Integration\ShopwareClient;
use App\Integration\ShopwareRequestException;
use App\Message\ExportShopwareCatalogue;
use App\Service\InventorySyncOutboxService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsMessageHandler]
final class ExportShopwareCatalogueHandler
{
    private array $referenceCache = [];

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly ShopwareClient $client,
        private readonly CatalogueExportReferences $references,
        private readonly CatalogueExportSelection $selection,
        private readonly ShopwareCataloguePayload $builder,
        private readonly IntegrationImportLogger $logger,
        private readonly ImportCancellation $cancellation,
        private readonly InventorySyncOutboxService $outbox,
        private readonly ?MessageBusInterface $bus = null,
    )
    {
    }

    public function __invoke(ExportShopwareCatalogue $message): void
    {
        if ($this->bus !== null) {
            try {
                $workflow = new \App\Integration\CatalogueExportWorkflow(
                    $this->manager, $this->references, $this->selection,
                    $this->logger, $this->cancellation, $this->bus,
                );
                $workflow->handle($message, 'shopware', [
                    'build' => $this->builder->build(...),
                    'destination' => fn ($connection, $settings, $url, $secrets) => $this->findTarget($url, $secrets, 'sales-channel', $settings['salesChannelId']),
                    'preview' => $this->previewBatch(...),
                    'publish' => $this->publish(...),
                ]);
            } finally {
                $this->referenceCache = [];
            }

            return;
        }
        foreach ([$message->tenantId, $message->connectionId, $message->planId, $message->runId] as $id) {
            if (!Uuid::isValid($id)) {
                return;
            }
        }
        $database = $this->manager->getConnection();
        $lockKey = 'catalogue-export:'.$message->tenantId.':'.$message->connectionId;
        if (!$database->fetchOne('SELECT pg_try_advisory_lock(hashtextextended(:key, 0))', ['key' => $lockKey])) {
            throw new RecoverableMessageHandlingException('Another catalogue publication is running.');
        }
        try {
            $this->execute($message);
        } finally {
            $this->referenceCache = [];
            $database->executeQuery('SELECT pg_advisory_unlock(hashtextextended(:key, 0))', ['key' => $lockKey]);
            $this->manager->clear();
        }
    }

    private function execute(ExportShopwareCatalogue $message): void
    {
        $database = $this->manager->getConnection();
        $connection = $this->manager->getRepository(IntegrationConnection::class)->findOneBy([
            'id' => Uuid::fromString($message->connectionId),
            'tenant' => Uuid::fromString($message->tenantId),
        ]);
        $run = $this->manager->getRepository(IntegrationImportRun::class)->findOneBy([
            'id' => Uuid::fromString($message->runId),
            'tenant' => Uuid::fromString($message->tenantId),
            'connection' => $connection,
        ]);
        $plan = $database->fetchAssociative(
            'SELECT * FROM integration_export_plans WHERE id = :id AND tenant_id = :tenant AND connection_id = :connection AND run_id = :run',
            ['id' => $message->planId, 'tenant' => $message->tenantId, 'connection' => $message->connectionId, 'run' => $message->runId],
        );
        if (!$connection instanceof IntegrationConnection || !$run instanceof IntegrationImportRun || !$plan || !in_array($run->getStatus(), ['queued', 'running'], true)) {
            return;
        }
        if (($run->getStatus() === 'running' || $plan['work_phase'] !== null)
            && ($plan['work_token'] ?? null) !== ($message->workToken ?? null)) {
            return; // An already acknowledged chunk cannot advance this run twice.
        }
        try {
            $this->cancellation->throwIfCancelled($run->getId());
            if ($connection->getConnectorKey() !== 'shopware' || !$connection->isEnabled() || $connection->getStatus() !== 'active') {
                throw new \DomainException('The Shopware connection is not active.');
            }
            $settings = json_decode($plan['settings'], true, flags: JSON_THROW_ON_ERROR);
            if ($plan['sync_mode'] === 'automatic' && !($settings['automaticSync'] ?? false)) {
                throw new \DomainException('Automatic catalogue synchronization is disabled.');
            }
            if (CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? []) !== $plan['settings_hash']) {
                throw new \DomainException('Export settings changed. Build a new preview.');
            }
            $secrets = $this->references->credentials($connection, $this->manager);
            $baseUrl = $connection->getConfiguration()['baseUrl'];
            $preview = $message->preview && !($plan['sync_mode'] !== null && $plan['status'] === 'publishing');
            if (!$preview && ($settings['_destinationUrl'] ?? null) !== $baseUrl) {
                throw new \DomainException('The destination endpoint changed or the preview predates this guard. Build a new preview.');
            }
            $settings['_destinationUrl'] = $baseUrl;
            $channel = $this->findTarget($baseUrl, $secrets, 'sales-channel', $settings['salesChannelId']);
            if (!$channel || !($channel['active'] ?? false)) {
                throw new \DomainException('The destination sales channel is missing or inactive.');
            }
            $database->executeStatement(
                'UPDATE integration_import_runs SET processed_items = 0, created_items = 0, updated_items = 0, failed_items = 0 WHERE id = :id AND tenant_id = :tenant',
                ['id' => $message->runId, 'tenant' => $message->tenantId],
            );
            $this->manager->refresh($run);
            $run->start(0, $preview ? 'exportReferences' : 'exportPreflight');
            $this->manager->flush();
            if ($preview) {
                $settings = $this->references->standardMappings($connection, $this->manager, $settings);
                $database->update('integration_export_plans', ['settings' => json_encode($settings, JSON_THROW_ON_ERROR)], ['id' => $message->planId]);
                // Restarting preview delivery is safe: no remote writes have occurred.
                $database->delete('integration_export_items', ['plan_id' => $message->planId]);
                $run->setCurrentStage('exportSelecting');
                $this->manager->flush();
                $selectionSettings = $settings;
                if ($plan['product_ids'] !== null) {
                    $selectionSettings['scope'] = 'selected';
                    $selectionSettings['categoryIds'] = [];
                    $selectionSettings['brandIds'] = [];
                    $selectionSettings['manufacturerIds'] = [];
                    $selectionSettings['productIds'] = json_decode($plan['product_ids'], true, flags: JSON_THROW_ON_ERROR);
                    $selectionSettings['includeVariants'] = false;
                }
                $run->updateTotalItems($this->selection->count($database, $connection, $selectionSettings));
                $run->setCurrentStage('exportPreview');
                $this->manager->flush();
                $batch = [];
                foreach ($this->selection->selectedIds($database, $connection, $selectionSettings) as $id) {
                    $batch[] = $id;
                    if (count($batch) === 25) {
                        $this->previewBatch($message, $connection, $run, $settings, $baseUrl, $secrets, $batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    $this->previewBatch($message, $connection, $run, $settings, $baseUrl, $secrets, $batch);
                }
                if ($run->getProcessedItems() === 0) {
                    throw new \DomainException('No products match this selection.');
                }
                $status = $run->getFailedItems() > 0 ? 'blocked' : 'ready';
                if ($plan['sync_mode'] !== null && $status === 'ready') {
                    // A sync request authorizes publication, but never bypasses preview validation.
                    $database->update('integration_export_plans', ['status' => 'publishing'], ['id' => $message->planId]);
                    $database->executeStatement(
                        'UPDATE integration_import_runs SET processed_items = 0, created_items = 0, updated_items = 0, failed_items = 0 WHERE id = :id AND tenant_id = :tenant',
                        ['id' => $message->runId, 'tenant' => $message->tenantId],
                    );
                    $this->manager->refresh($run);
                    $this->publish($message, $connection, $run, $settings, $baseUrl, $secrets, $channel);
                    $status = $run->getFailedItems() > 0 ? 'failed' : 'completed';
                }
            } else {
                $run->updateTotalItems((int) $database->fetchOne('SELECT COUNT(*) FROM integration_export_items WHERE plan_id = :id', ['id' => $message->planId]));
                $this->manager->flush();
                $this->publish($message, $connection, $run, $settings, $baseUrl, $secrets, $channel);
                $status = $run->getFailedItems() > 0 ? 'failed' : 'completed';
            }
            $this->cancellation->throwIfCancelled($run->getId());
            $run->complete();
            $database->update('integration_export_plans', ['status' => $status], ['id' => $message->planId]);
            $this->logger->info($run, 'completed', 'integrationLog.exportCompleted', ['createdItems' => $run->getCreatedItems(), 'updatedItems' => $run->getUpdatedItems(), 'failedItems' => $run->getFailedItems()]);
            $this->manager->flush();
        } catch (ImportCancelledException) {
            $database->update('integration_export_plans', ['status' => 'cancelled'], ['id' => $message->planId]);
        } catch (\Throwable $exception) {
            $run->fail($exception->getMessage());
            $database->update('integration_export_plans', ['status' => 'failed'], ['id' => $message->planId]);
            $this->logger->error($run, 'failed', 'integrationLog.exportFailed', ['reason' => $exception->getMessage()]);
            $this->manager->flush();
        }
    }

    private function previewBatch(
        ExportShopwareCatalogue $message,
        IntegrationConnection $connection,
        IntegrationImportRun $run,
        array $settings,
        string $baseUrl,
        array $secrets,
        array $ids,
        ?float $deadline = null,
    ): bool
    {
        $this->cancellation->throwIfCancelled($run->getId());
        $products = $this->manager->getRepository(Product::class)->findBy(['tenant' => $connection->getTenant(), 'id' => $ids]);
        $externalIds = [];
        $skus = [];
        foreach ($products as $product) {
            $externalIds[] = $this->builder->productId($connection, $this->manager, $product, $settings);
            $skus[] = $product->getSku();
        }
        $targets = $this->targetProducts($baseUrl, $secrets, $externalIds, array_filter($skus));
        $finished = true;
        foreach ($products as $product) {
            $this->cancellation->throwIfCancelled($run->getId());
            $id = $this->builder->productId($connection, $this->manager, $product, $settings);
            $target = $targets[$id] ?? null;
            $built = $this->builder->build($product, $connection, $this->manager, $settings, $target === null);
            $syncSourceHash = self::hash($target !== null
                ? $built
                : $this->builder->build($product, $connection, $this->manager, $settings, false));
            $issues = $built['issues'];
            $baseline = $this->manager->getConnection()->fetchAssociative(
                'SELECT * FROM integration_catalogue_publications WHERE tenant_id = :tenant AND connection_id = :connection AND product_id = :product',
                ['tenant' => $message->tenantId, 'connection' => $message->connectionId, 'product' => (string) $product->getId()],
            );
            $syncMode = $this->manager->getConnection()->fetchOne('SELECT sync_mode FROM integration_export_plans WHERE id = :id', ['id' => $message->planId]);
            if ($syncMode === 'automatic' && $baseline && $baseline['target_hash'] !== null && $target !== null
                && self::targetHash($target, json_decode($baseline['owned_payload'], true)) !== $baseline['target_hash']) {
                $issues[] = 'Shopware-owned fields changed since the last Connect publication. Review a manual preview before overwriting.';
            }
            $identityOwner = $this->manager->getRepository(IntegrationEntityMapping::class)->findOneBy([
                'tenant' => $connection->getTenant(), 'connection' => $connection, 'entityType' => 'product', 'externalId' => $id,
            ]);
            if ($identityOwner instanceof IntegrationEntityMapping && $identityOwner->getLocalId() != $product->getId()) {
                $issues[] = 'This Shopware identity is already assigned to another Connect product.';
            }
            foreach ($targets as $otherId => $other) {
                if ($otherId !== $id && ($other['productNumber'] ?? null) === $product->getSku()) {
                    $issues[] = 'This product number already exists in Shopware. Map the product explicitly before exporting.';
                }
            }
            if ($target !== null && ($target['parentId'] ?? null) !== ($built['payload']['parentId'] ?? null)) {
                $issues[] = 'The existing destination product has a different parent.';
            }
            $sourceHash = self::hash($built);
            $built['syncSourceHash'] = $syncSourceHash;
            if ($target !== null) {
                $this->reuseAssociationIds($built['payload'], $target);
            }
            $this->validateReferences($baseUrl, $secrets, $built, $issues);
            $this->manager->getConnection()->transactional(function () use ($message, $connection, $product, $run, $syncSourceHash, $built, $sourceHash, $target, $id, $issues): void {
                $this->recordAttempt($message, $connection, $product, $syncSourceHash);
                $this->manager->getConnection()->insert('integration_export_items', [
                    'id' => (string) Uuid::v7(),
                    'plan_id' => $message->planId,
                    'product_id' => (string) $product->getId(),
                    'parent_id' => $product->getParent() ? (string) $product->getParent()->getId() : null,
                    'external_id' => $id,
                    'name' => mb_substr($built['name'], 0, 255),
                    'sku' => $product->getSku() ?? '',
                    'action' => $target === null ? 'create' : 'update',
                    'payload' => json_encode($built, JSON_THROW_ON_ERROR),
                    'source_hash' => $sourceHash,
                    'target_hash' => $target === null ? null : self::targetHash($target, $built['payload']),
                    'issues' => json_encode(array_values(array_unique($issues)), JSON_THROW_ON_ERROR),
                ]);
                if ($issues === []) {
                    $run->recordSuccess($target === null);
                } else {
                    $run->recordFailure();
                }
                // Item and progress commit together, including after redelivery.
                $this->manager->flush();
            });
            if ($deadline !== null && microtime(true) >= $deadline) {
                $finished = false;
                break;
            }
        }
        $this->manager->flush();
        // Bound ORM memory without detaching the active run/connection.
        foreach ($products as $product) {
            $this->manager->detach($product);
        }
        $this->trimManagedEntities();
        $this->referenceCache = [];

        return $finished;
    }

    private function publish(
        ExportShopwareCatalogue $message,
        IntegrationConnection $connection,
        IntegrationImportRun $run,
        array $settings,
        string $baseUrl,
        array $secrets,
        array $channelData,
        bool $chunked = false,
    ): void
    {
        $database = $this->manager->getConnection();
        $expectedSettingsHash = CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? []);
        // Validate every source before the first destination write.
        $checked = 0;
        foreach ($database->executeQuery('SELECT * FROM integration_export_items WHERE plan_id = :id'.($chunked ? ' AND FALSE' : '').' ORDER BY parent_id NULLS FIRST, product_id', ['id' => $message->planId])->iterateAssociative() as $item) {
            $this->cancellation->throwIfCancelled($run->getId());
            $this->assertCurrentConfiguration($message, $expectedSettingsHash, $baseUrl);
            if ($item['status'] === 'published') {
                continue;
            }
            $product = $this->manager->getRepository(Product::class)->findOneBy(['tenant' => $connection->getTenant(), 'id' => $item['product_id']]);
            if (!$product instanceof Product || self::hash($this->builder->build($product, $connection, $this->manager, $settings, $item['action'] === 'create')) !== $item['source_hash']) {
                throw new \DomainException('Catalogue data changed after preview. Build a new preview.');
            }
            $this->manager->detach($product);
            $this->trimManagedEntities();
            if (++$checked % 25 === 0) {
                $run->setCurrentStage('exportPreflight');
                $this->manager->flush();
            }
        }
        $run->setCurrentStage('exporting');
        $this->manager->flush();
        $channel = $this->manager->getRepository(IntegrationSalesChannel::class)->findOneBy([
            'tenant' => $connection->getTenant(), 'connection' => $connection, 'externalId' => $settings['salesChannelId'],
        ]);
        if (!$channel instanceof IntegrationSalesChannel) {
            $channel = new IntegrationSalesChannel($connection->getTenant(), $connection, $settings['salesChannelId'], $channelData['name'] ?? 'Shopware', $channelData['typeId'] ?? null, true, $channelData);
            $this->manager->persist($channel);
            $this->manager->flush();
        }
        $deadline = microtime(true) + 10;
        if (!$chunked) {
            \App\Integration\CatalogueExportWorkflow::repairCounts($this->manager, $message, $run, false);
        }
        do {
            $rows = $database->fetchAllAssociative(
                "SELECT * FROM integration_export_items WHERE plan_id = :plan AND status = 'pending' ORDER BY parent_id NULLS FIRST, product_id LIMIT 25",
                ['plan' => $message->planId],
            );
            if ($rows === []) {
                return;
            }
            $targets = $this->readProductTargets($baseUrl, $secrets, array_column($rows, 'external_id'));
            $this->primeReferenceCache($baseUrl, $secrets, array_map(
                static fn (array $item): array => json_decode($item['payload'], true, flags: JSON_THROW_ON_ERROR),
                $rows,
            ));
            $prepared = [];
            foreach ($rows as $item) {
                // A child can only enter a later request after its parent is acknowledged.
                if ($item['parent_id'] !== null && isset($prepared[$item['parent_id']])) {
                    break;
                }
                try {
                    $prepared[$item['product_id']] = $this->preparePublicationItem(
                        $message, $connection, $run, $settings, $baseUrl, $secrets,
                        $item, $targets[$item['external_id']] ?? null,
                    );
                } catch (\Throwable $exception) {
                    $this->recordPublicationFailure($message, $run, $item, $exception);
                }
                if ($chunked && microtime(true) >= $deadline) {
                    break;
                }
            }
            if ($prepared !== []) {
                $this->writePublicationBatch(
                    $message, $connection, $run, $settings, $baseUrl, $secrets,
                    $channel, array_values($prepared), $chunked ? $deadline : null,
                );
            }
            $this->trimManagedEntities();
            $this->referenceCache = [];
        } while (!$chunked);
    }

    private function preparePublicationItem(
        ExportShopwareCatalogue $message,
        IntegrationConnection $connection,
        IntegrationImportRun $run,
        array $settings,
        string $url,
        array $secrets,
        array $item,
        ?array $target,
    ): array
    {
        $hash = CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? []);
        $this->cancellation->throwIfCancelled($run->getId());
        $this->assertCurrentConfiguration($message, $hash, $url);
        if ($item['parent_id'] !== null && $this->manager->getConnection()->fetchOne(
            'SELECT status FROM integration_export_items WHERE plan_id = :plan AND product_id = :parent',
            ['plan' => $message->planId, 'parent' => $item['parent_id']],
        ) !== 'published') {
            throw new \DomainException('The required parent product was not published.');
        }
        $built = json_decode($item['payload'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertDestinationSnapshot($item, $built, $target);
        $product = $this->manager->getRepository(Product::class)->findOneBy([
            'tenant' => $connection->getTenant(), 'id' => $item['product_id'],
        ]);
        $currentBuilt = $product instanceof Product
            ? $this->builder->build($product, $connection, $this->manager, $settings, $item['action'] === 'create')
            : null;
        if ($currentBuilt === null || self::hash($currentBuilt) !== $item['source_hash']) {
            throw new \DomainException('Catalogue data changed after preview. Build a new preview.');
        }
        $identityOwner = $this->manager->getRepository(IntegrationEntityMapping::class)->findOneBy([
            'tenant' => $connection->getTenant(), 'connection' => $connection,
            'entityType' => 'product', 'externalId' => $item['external_id'],
        ]);
        if ($identityOwner instanceof IntegrationEntityMapping && $identityOwner->getLocalId() != $product->getId()) {
            throw new \DomainException('The destination identity is already owned by another local product.');
        }
        $issues = [];
        $this->validateReferences($url, $secrets, $built, $issues);
        if ($issues !== []) {
            throw new \DomainException(implode(' ', $issues));
        }
        $this->publishDependencies($message, $url, $secrets, $built['dependencies'] ?? [], $hash);
        foreach ($built['media'] as $media) {
            $this->assertCurrentConfiguration($message, $hash, $url);
            $this->cancellation->throwIfCancelled($run->getId());
            $remoteMedia = $this->findTarget($url, $secrets, 'media', $media['id']);
            if ($remoteMedia === null) {
                $this->client->writeCatalogueEntity($url, $secrets, 'media', ['id' => $media['id']]);
            }
            if ($remoteMedia === null || empty($remoteMedia['fileName'])) {
                $this->client->uploadCatalogueMedia($url, $secrets, $media['id'], $this->builder->mediaPath($connection, $media['storageKey']), $media['extension'], $media['mimeType']);
            }
        }
        $updatedBuilt = $item['action'] === 'update'
            ? $currentBuilt
            : $this->builder->build($product, $connection, $this->manager, $settings, false);
        foreach ($updatedBuilt['payload']['translations'] ?? [] as $language => $translation) {
            foreach ($translation['customFields'] ?? [] as $id => $value) {
                $definition = $built['expectedReferences']['customField'][$id] ?? null;
                if ($definition !== null) {
                    unset($updatedBuilt['payload']['translations'][$language]['customFields'][$id]);
                    $updatedBuilt['payload']['translations'][$language]['customFields'][$definition['name']] = $value;
                }
            }
        }

        return [
            'item' => $item,
            'built' => $built,
            'ownedPayload' => $updatedBuilt['payload'],
            'source' => $built['syncSourceHash'] ?? throw new \DomainException('Build a new preview to establish the publication source version.'),
        ];
    }

    private function assertDestinationSnapshot(array $item, array $built, ?array $target): bool
    {
        $intended = self::targetHash($built['payload'], $built['payload']);
        $actual = $target === null ? null : self::targetHash($target, $built['payload']);
        $acknowledge = ($item['write_started'] ?? false) && $actual === $intended;
        if ($item['action'] === 'update' && ($target === null || ($actual !== $item['target_hash'] && !$acknowledge))) {
            throw new \DomainException('Shopware data changed after preview. Build a new preview.');
        }
        if ($item['action'] === 'create' && $target !== null && $actual !== $intended) {
            throw new \DomainException('The destination product appeared after preview. Review it before retrying.');
        }

        return $acknowledge;
    }

    private function writePublicationBatch(
        ExportShopwareCatalogue $message,
        IntegrationConnection $connection,
        IntegrationImportRun $run,
        array $settings,
        string $url,
        array $secrets,
        IntegrationSalesChannel $channel,
        array $prepared,
        ?float $deadline = null,
    ): void
    {
        // Re-read after dependency/media preparation; no old ORM source or target is trusted.
        $this->trimManagedEntities();
        $targets = $this->readProductTargets($url, $secrets, array_column(array_column($prepared, 'item'), 'external_id'));
        $this->primeReferenceCache($url, $secrets, array_column($prepared, 'built'));
        $valid = [];
        $writes = [];
        foreach ($prepared as $entry) {
            try {
                $item = $entry['item'];
                $product = $this->manager->getRepository(Product::class)->findOneBy([
                    'tenant' => $connection->getTenant(), 'id' => $item['product_id'],
                ]);
                if (!$product instanceof Product || self::hash($this->builder->build(
                    $product, $connection, $this->manager, $settings, $item['action'] === 'create',
                )) !== $item['source_hash']) {
                    throw new \DomainException('Catalogue data changed after preview. Build a new preview.');
                }
                $built = $entry['built'];
                $issues = [];
                $this->validateReferences($url, $secrets, $built, $issues);
                if ($issues !== []) {
                    throw new \DomainException(implode(' ', $issues));
                }
                $acknowledge = $this->assertDestinationSnapshot($item, $built, $targets[$item['external_id']] ?? null);
                $valid[] = $entry;
                if (!$acknowledge) {
                    $writes[] = $built['payload'];
                }
            } catch (\Throwable $exception) {
                $this->recordPublicationFailure($message, $run, $entry['item'], $exception);
            }
        }
        if ($valid === []) {
            return;
        }
        $this->assertCurrentConfiguration($message, CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? []), $url);
        $this->cancellation->throwIfCancelled($run->getId());
        if ($writes !== []) {
            $this->manager->getConnection()->executeStatement(
                'UPDATE integration_export_items SET write_started = TRUE WHERE plan_id = :plan AND id IN (:ids)',
                ['plan' => $message->planId, 'ids' => array_column(array_column($valid, 'item'), 'id')],
                ['ids' => ArrayParameterType::STRING],
            );
            foreach ($valid as &$entry) {
                $entry['item']['write_started'] = true;
            }
            unset($entry);
            try {
                $this->client->writeCatalogueEntities($url, $secrets, 'product', $writes);
            } catch (\Throwable $exception) {
                // Transport/5xx failures may have committed remotely: reconcile on delayed redelivery.
                if ($exception instanceof IntegrationRetryLaterException || $exception instanceof TransportExceptionInterface) {
                    throw $exception;
                }
                if ($exception instanceof ShopwareRequestException && in_array($exception->statusCode, [401, 403], true)) {
                    throw $exception;
                }
                $validation = $exception instanceof ShopwareRequestException
                    && in_array($exception->statusCode, [400, 422], true);
                if ($validation && count($valid) > 1) {
                    $attempted = 0;
                    foreach ($valid as $entry) {
                        // Make progress even when the rejected request used the budget,
                        // then yield remaining pending records instead of serializing
                        // twenty-five slow single-item requests in one delivery.
                        if ($attempted > 0 && $deadline !== null && microtime(true) >= $deadline) {
                            return;
                        }
                        $this->writePublicationBatch($message, $connection, $run, $settings, $url, $secrets, $channel, [$entry], $deadline);
                        ++$attempted;
                    }

                    return;
                }
                foreach ($valid as $entry) {
                    $this->recordPublicationFailure($message, $run, $entry['item'], $exception);
                }

                return;
            }
            $targets = $this->readProductTargets($url, $secrets, array_column(array_column($valid, 'item'), 'external_id'));
        }
        foreach ($valid as $entry) {
            try {
                $target = $targets[$entry['item']['external_id']] ?? null;
                if ($target === null) {
                    throw new \DomainException('Shopware did not return the published product. Reconcile before retrying.');
                }
                $this->acknowledgePublicationItem($message, $connection, $run, $settings, $channel, $entry, $target);
            } catch (\Throwable $exception) {
                $this->recordPublicationFailure($message, $run, $entry['item'], $exception);
            }
        }
    }

    private function acknowledgePublicationItem(
        ExportShopwareCatalogue $message,
        IntegrationConnection $connection,
        IntegrationImportRun $run,
        array $settings,
        IntegrationSalesChannel $channel,
        array $entry,
        array $target,
    ): void
    {
        $database = $this->manager->getConnection();
        $item = $entry['item'];
        // Mapping, baseline, stock outbox, item and counters acknowledge one source atomically.
        $database->transactional(function () use ($database, $message, $connection, $run, $settings, $channel, $entry, $item, $target): void {
            $product = $this->manager->getRepository(Product::class)->findOneBy([
                'tenant' => $connection->getTenant(), 'id' => $item['product_id'],
            ]);
            if (!$product instanceof Product) {
                throw new \DomainException('The source product no longer exists.');
            }
            $mapping = $this->manager->getRepository(IntegrationEntityMapping::class)->findOneBy([
                'tenant' => $connection->getTenant(), 'connection' => $connection,
                'entityType' => 'product', 'externalId' => $item['external_id'],
            ]);
            if ($mapping instanceof IntegrationEntityMapping && $mapping->getLocalId() != $product->getId()) {
                throw new \DomainException('The destination identity is already owned by another local product.');
            }
            if (!$mapping instanceof IntegrationEntityMapping) {
                $this->manager->persist(new IntegrationEntityMapping($connection->getTenant(), $connection, 'product', $item['external_id'], $product->getId()));
            }
            $publication = $this->manager->getRepository(ProductChannelPublication::class)->findOneBy([
                'tenant' => $connection->getTenant(), 'product' => $product, 'salesChannel' => $channel,
            ]);
            $visibility = $settings['publicationMode'] === 'deactivate' ? 0 : 30;
            if (!$publication instanceof ProductChannelPublication) {
                $this->manager->persist(new ProductChannelPublication(
                    $connection->getTenant(), $product, $channel, $visibility, $item['external_id'],
                ));
            } else {
                $publication->update($visibility, $item['external_id']);
            }
            $database->executeStatement(<<<'SQL'
UPDATE integration_catalogue_publications SET source_hash = :source, settings_hash = :hash,
    target_hash = :target, owned_payload = :payload, published_at = CURRENT_TIMESTAMP
WHERE tenant_id = :tenant AND connection_id = :connection AND product_id = :product
SQL, [
                'source' => $entry['source'], 'hash' => CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? []),
                'target' => self::targetHash($target, $entry['ownedPayload']),
                'payload' => json_encode($entry['ownedPayload'], JSON_THROW_ON_ERROR),
                'tenant' => $message->tenantId, 'connection' => $message->connectionId, 'product' => $item['product_id'],
            ]);
            if (($connection->getConfiguration()['importSettings']['stockAuthority'] ?? '') === 'connect') {
                $this->outbox->queue($connection->getTenant(), $product, $this->manager);
            }
            $database->update('integration_export_items', ['status' => 'published', 'result' => null], ['id' => $item['id'], 'plan_id' => $message->planId]);
            $run->recordSuccess($item['action'] === 'create');
            $this->manager->flush();
        });
        $this->logger->info($run, 'exporting', 'integrationLog.exportProduct', ['sku' => $item['sku'], 'action' => $item['action']]);
        $this->trimManagedEntities();
    }

    private function recordPublicationFailure(
        ExportShopwareCatalogue $message,
        IntegrationImportRun $run,
        array $item,
        \Throwable $exception,
    ): void
    {
        if ($exception instanceof ImportCancelledException || $exception instanceof IntegrationRetryLaterException
            || $exception instanceof TransportExceptionInterface || $exception instanceof \Doctrine\DBAL\Exception
            || !$this->manager->isOpen()) {
            throw $exception;
        }
        $database = $this->manager->getConnection();
        $database->transactional(function () use ($database, $message, $run, $item, $exception): void {
            $changed = $database->executeStatement(
                "UPDATE integration_export_items SET status = 'failed', result = :reason WHERE id = :id AND plan_id = :plan AND status = 'pending'",
                ['reason' => mb_substr($exception->getMessage(), 0, 2000), 'id' => $item['id'], 'plan' => $message->planId],
            );
            if ($changed > 0) {
                $run->recordFailure();
                $this->manager->flush();
            }
        });
        $this->logger->error($run, 'exporting', 'integrationLog.exportProductFailed', ['sku' => $item['sku'], 'reason' => $exception->getMessage()]);
    }

    private function readProductTargets(string $url, array $secrets, array $ids): array
    {
        $response = $this->client->searchPage($url, $secrets, 'product', [
            'ids' => array_values(array_unique($ids)),
            'limit' => 25,
            'associations' => self::productAssociations(),
        ]);
        $targets = [];
        foreach ($response['data'] ?? [] as $record) {
            $targets[$record['id']] = self::attributes($record, $response['included'] ?? []);
        }

        return $targets;
    }

    /** Fresh, bounded reference snapshots for this request, never cross-tenant/connection caches. */
    private function primeReferenceCache(string $url, array $secrets, array $builds): void
    {
        $this->referenceCache = [];
        $byType = [];
        foreach ($builds as $built) {
            foreach ($built['references'] as $type => $ids) {
                foreach ($ids as $id) {
                    $byType[$type][$id] = $id;
                    $this->referenceCache[$type.':'.$id] = null;
                }
            }
        }
        foreach ($byType as $type => $ids) {
            foreach (array_chunk(array_values($ids), 100) as $page) {
                $response = $this->client->searchPage($url, $secrets, CatalogueExportReferences::ENTITIES[$type], [
                    'ids' => $page,
                    'limit' => 100,
                ]);
                foreach ($response['data'] ?? [] as $record) {
                    $this->referenceCache[$type.':'.$record['id']] = self::attributes($record, $response['included'] ?? []);
                }
            }
        }
    }

    private function validateReferences(string $baseUrl, array $secrets, array &$built, array &$issues): void
    {
        $planned = [];
        foreach ($built['dependencies'] ?? [] as $dependency) {
            $planned[$dependency['type']][$dependency['payload']['id']] = $dependency['payload'];
        }
        foreach ($built['dependencies'] ?? [] as $dependency) {
            $payload = $dependency['payload'];
            $existing = $this->findTarget($baseUrl, $secrets, $dependency['entity'], $payload['id']);
            if ($existing !== null && self::dependencyHash($existing, $payload) !== self::dependencyHash($payload, $payload)) {
                $issues[] = 'A Connect-created '.$dependency['type'].' was changed. Map it explicitly or reconcile the reference before publishing.';
            }
            if ($existing === null && in_array($dependency['type'], ['customField', 'customFieldSet'], true)) {
                $response = $this->client->searchPage($baseUrl, $secrets, $dependency['entity'], [
                    'limit' => 1,
                    'filter' => [['type' => 'equals', 'field' => 'name', 'value' => $payload['name']]],
                ]);
                if (($response['data'] ?? []) !== []) {
                    $issues[] = 'The destination already has a custom field/set with this technical name. Map it explicitly: '.$payload['name'];
                }
            }
            foreach ($dependency['requires'] as $type => $ids) {
                foreach ($ids as $id) {
                    if (isset($planned[$type][$id])) {
                        continue;
                    }
                    $entity = CatalogueExportReferences::ENTITIES[$type] ?? ShopwareCatalogueDependencies::ENTITIES[$type];
                    if ($this->findTarget($baseUrl, $secrets, $entity, $id) === null) {
                        $issues[] = 'Missing destination dependency: '.$type.' '.$id;
                    }
                }
            }
        }
        foreach ($built['references'] as $type => $ids) {
            foreach ($ids as $id) {
                $key = $type.':'.$id;
                if (!array_key_exists($key, $this->referenceCache)) {
                    $this->referenceCache[$key] = $this->findTarget($baseUrl, $secrets, CatalogueExportReferences::ENTITIES[$type], $id);
                }
                $reference = $this->referenceCache[$key] ?? $planned[$type][$id] ?? null;
                if ($reference === null) {
                    $issues[] = 'Destination '.$type.' no longer exists: '.$id;
                    continue;
                }
                $expected = $built['expectedReferences'][$type][$id] ?? null;
                if ($type === 'currency' && $expected !== null && ($reference['isoCode'] ?? null) !== $expected) {
                    $issues[] = 'Currency mapping would change the currency without conversion.';
                }
                if ($type === 'tax' && $expected !== null && abs((float) ($reference['taxRate'] ?? -1) - (float) $expected) > 0.0001) {
                    $issues[] = 'The mapped Shopware tax rate differs from the source selling-price tax rate.';
                }
                if ($type === 'customField') {
                    $definition = ['name' => $reference['name'], 'type' => $reference['type'] ?? ''];
                    if (isset($built['expectedReferences']['customField'][$id])
                        && $built['expectedReferences']['customField'][$id] !== $definition) {
                        $issues[] = 'The destination custom field definition changed after preview.';
                    }
                    $built['expectedReferences']['customField'][$id] = $definition;
                    foreach ($built['payload']['translations'] ?? [] as $language => $translation) {
                        if (!array_key_exists($id, $translation['customFields'] ?? [])) {
                            continue;
                        }
                        $value = $translation['customFields'][$id];
                        $valid = match ($reference['type'] ?? '') {
                            'text', 'html', 'datetime' => is_string($value) || $value === null,
                            'int' => is_int($value) || $value === null,
                            'float' => is_numeric($value) || $value === null,
                            'bool' => is_bool($value) || $value === null,
                            'json' => true,
                            default => false,
                        };
                        if (!$valid) {
                            $issues[] = 'Incompatible destination custom field type: '.$reference['name'];
                        }
                        unset($built['payload']['translations'][$language]['customFields'][$id]);
                        $built['payload']['translations'][$language]['customFields'][$reference['name']] = $value;
                    }
                }
            }
        }
    }

    private function recordAttempt(
        ExportShopwareCatalogue $message,
        IntegrationConnection $connection,
        Product $product,
        string $source,
    ): void
    {
        $hash = CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? []);
        $this->manager->getConnection()->executeStatement(<<<'SQL'
INSERT INTO integration_catalogue_publications
    (tenant_id, connection_id, product_id, settings_hash, source_hash, owned_payload, attempt_hash)
VALUES (:tenant, :connection, :product, :hash, :source, '{}', :attempt)
ON CONFLICT (tenant_id, connection_id, product_id) DO UPDATE SET
    attempt_hash = EXCLUDED.attempt_hash, attempted_at = CURRENT_TIMESTAMP
SQL, [
            'tenant' => $message->tenantId, 'connection' => $message->connectionId,
            'product' => (string) $product->getId(), 'hash' => $hash, 'source' => $source, 'attempt' => $hash.':'.$source,
        ]);
    }

    private function publishDependencies(
        ExportShopwareCatalogue $message,
        string $url,
        array $secrets,
        array $dependencies,
        string $settingsHash,
    ): void
    {
        foreach ($dependencies as $dependency) {
            $this->cancellation->throwIfCancelled(Uuid::fromString($message->runId));
            $this->assertCurrentConfiguration($message, $settingsHash, $url);
            $payload = $dependency['payload'];
            $type = match ($dependency['type']) {
                'deliveryTime' => 'delivery_time',
                'customField' => 'custom_field',
                'customFieldSet' => 'custom_field_set',
                'propertyGroup' => 'property_group',
                default => $dependency['type'],
            };
            $owner = $this->manager->getConnection()->fetchAssociative(
                'SELECT tenant_id, local_id FROM integration_entity_mappings WHERE connection_id = :connection AND entity_type = :type AND external_id = :external',
                ['connection' => $message->connectionId, 'type' => $type, 'external' => $payload['id']],
            );
            if ($owner && ($owner['tenant_id'] !== $message->tenantId || $owner['local_id'] !== $dependency['localId'])) {
                throw new \DomainException('The reference identity belongs to a different catalogue record.');
            }
            $existing = $this->findTarget($url, $secrets, $dependency['entity'], $payload['id']);
            if ($existing === null) {
                $this->client->writeCatalogueEntity($url, $secrets, str_replace('-', '_', $dependency['entity']), $payload);
            } elseif (self::dependencyHash($existing, $payload) !== self::dependencyHash($payload, $payload)) {
                throw new \DomainException('The destination reference changed during publication.');
            }
            $this->manager->getConnection()->executeStatement(<<<'SQL'
INSERT INTO integration_entity_mappings (id, tenant_id, connection_id, entity_type, external_id, local_id)
VALUES (:id, :tenant, :connection, :type, :external, :local)
ON CONFLICT (connection_id, entity_type, external_id) DO NOTHING
SQL, [
                'id' => (string) Uuid::v7(), 'tenant' => $message->tenantId, 'connection' => $message->connectionId,
                'type' => $type, 'external' => $payload['id'], 'local' => $dependency['localId'],
            ]);
        }
    }

    private static function dependencyHash(array $record, array $payload): string
    {
        // Compare only fields this creation payload owns; ignore Shopware-generated metadata.
        $owned = [];
        foreach ($payload as $key => $value) {
            if (in_array($key, ['id', 'relations'], true)) {
                continue;
            }
            if ($key === 'translations') {
                $translations = [];
                foreach ($record['translations'] ?? [] as $language => $translation) {
                    $translations[$translation['languageId'] ?? $language] = $translation;
                }
                foreach ($value as $language => $translation) {
                    foreach ($translation as $field => $expected) {
                        $owned[$key][$language][$field] = $translations[$language][$field] ?? null;
                    }
                }
            } else {
                $actual = $record[$key] ?? null;
                $owned[$key] = (is_float($value) || is_int($value)) && is_numeric($actual) ? (float) $actual : $actual;
            }
        }

        return self::hash($owned);
    }

    private function assertCurrentConfiguration(
        ExportShopwareCatalogue $message,
        string $hash,
        string $url,
    ): void
    {
        $row = $this->manager->getConnection()->fetchAssociative(
            'SELECT enabled, status, configuration, directions FROM integration_connections WHERE tenant_id = :tenant AND id = :connection',
            ['tenant' => $message->tenantId, 'connection' => $message->connectionId],
        );
        $configuration = $row ? json_decode($row['configuration'], true, flags: JSON_THROW_ON_ERROR) : [];
        if (!$row || !$row['enabled'] || $row['status'] !== 'active'
            || !in_array('channel', json_decode($row['directions'], true), true)
            || ($configuration['baseUrl'] ?? null) !== $url
            || CatalogueExportSettings::hash($configuration['exportSettings'] ?? []) !== $hash) {
            throw new \DomainException('The connection or export configuration changed. Publication stopped; build a new preview.');
        }
    }

    private function findTarget(string $baseUrl, array $secrets, string $entity, string $id): ?array
    {
        $criteria = ['ids' => [$id], 'limit' => 1];
        if ($entity === 'product') {
            $criteria['associations'] = self::productAssociations();
        } elseif (in_array($entity, ['category', 'product-manufacturer', 'property-group', 'property-group-option', 'unit', 'delivery-time'], true)) {
            $criteria['associations'] = ['translations' => []];
        }
        $response = $this->client->searchPage($baseUrl, $secrets, $entity, $criteria);
        $record = $response['data'][0] ?? null;

        return $record === null ? null : self::attributes($record, $response['included'] ?? []);
    }

    private function trimManagedEntities(): void
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

    private function targetProducts(string $baseUrl, array $secrets, array $ids, array $skus): array
    {
        $targets = [];
        $this->client->forEachEntityPage($baseUrl, $secrets, 'product', function (int $total, array $records, array $included) use (&$targets): void {
            foreach ($records as $record) {
                $targets[$record['id']] = self::attributes($record, $included);
            }
        }, self::productAssociations(), 100, ['filter' => [['type' => 'multi', 'operator' => 'OR', 'queries' => [
            ['type' => 'equalsAny', 'field' => 'id', 'value' => $ids],
            ['type' => 'equalsAny', 'field' => 'productNumber', 'value' => array_values($skus)],
        ]]]]);

        return $targets;
    }

    public static function targetHash(array $target, array $payload): string
    {
        $owned = [];
        foreach (array_keys($payload) as $key) {
            if (!in_array($key, ['id', 'stock', 'visibilities', 'media', 'translations', 'categories', 'properties', 'options', 'configuratorSettings'], true)) {
                $owned[$key] = $target[$key] ?? null;
            }
        }
        foreach (['categories', 'properties', 'options'] as $key) {
            if (isset($payload[$key])) {
                $owned[$key] = array_map(static fn (array $item): string => $item['id'], $target[$key] ?? []);
            }
        }
        if (isset($payload['price'])) {
            $owned['price'] = array_map(static function (array $price): array {
                $normalized = [
                    'currencyId' => $price['currencyId'],
                    'gross' => (float) $price['gross'],
                    'net' => (float) $price['net'],
                    'linked' => (bool) ($price['linked'] ?? false),
                ];
                foreach (['listPrice', 'regulationPrice'] as $key) {
                    $value = $price[$key] ?? null;
                    $normalized[$key] = $value === null ? null : [
                        'gross' => (float) $value['gross'],
                        'net' => (float) $value['net'],
                        'linked' => (bool) ($value['linked'] ?? false),
                    ];
                }

                return $normalized;
            }, $target['price'] ?? []);
        }
        if (isset($payload['visibilities'])) {
            $channelIds = array_column($payload['visibilities'], 'salesChannelId');
            $owned['visibilities'] = array_values(array_map(
                static fn (array $item): array => [
                    'salesChannelId' => $item['salesChannelId'],
                    'visibility' => (int) $item['visibility'],
                ],
                array_filter(
                    $target['visibilities'] ?? [],
                    static fn (array $item): bool => in_array($item['salesChannelId'], $channelIds, true),
                ),
            ));
        }
        if (isset($payload['media'])) {
            $owned['media'] = array_map(static fn (array $item): array => ['mediaId' => $item['mediaId'], 'position' => $item['position']], $target['media'] ?? []);
        }
        if (isset($payload['configuratorSettings'])) {
            $owned['configuratorSettings'] = array_map(static fn (array $item): string => $item['optionId'], $target['configuratorSettings'] ?? []);
        }
        if (isset($payload['translations'])) {
            $translations = [];
            foreach ($target['translations'] ?? [] as $key => $translation) {
                $languageId = $translation['languageId'] ?? $key;
                $translations[$languageId] = $translation;
            }
            foreach ($payload['translations'] as $languageId => $translation) {
                foreach ($translation as $key => $value) {
                    if ($key === 'customFields') {
                        foreach (array_keys($value) as $name) {
                            $owned['translations'][$languageId]['customFields'][$name] = $translations[$languageId]['customFields'][$name] ?? null;
                        }
                    } else {
                        $owned['translations'][$languageId][$key] = $translations[$languageId][$key] ?? null;
                    }
                }
            }
        }

        return self::hash($owned);
    }

    private static function productAssociations(): array
    {
        return ['categories' => [], 'properties' => [], 'options' => [], 'translations' => [], 'media' => [], 'visibilities' => [], 'configuratorSettings' => []];
    }

    private static function attributes(array $record, array $included): array
    {
        $attributes = $record['attributes'] ?? $record;
        $byId = [];
        foreach ($included as $related) {
            $byId[($related['type'] ?? '').':'.$related['id']] = ['id' => $related['id'], ...($related['attributes'] ?? [])];
        }
        foreach ($record['relationships'] ?? [] as $name => $relationship) {
            $data = $relationship['data'] ?? null;
            if (!is_array($data) || !array_is_list($data)) {
                continue;
            }
            $attributes[$name] = [];
            foreach ($data as $reference) {
                $attributes[$name][] = $byId[($reference['type'] ?? '').':'.$reference['id']] ?? ['id' => $reference['id']];
            }
        }

        return $attributes;
    }

    private function reuseAssociationIds(array &$payload, array $target): void
    {
        foreach (['visibilities' => 'salesChannelId', 'media' => 'mediaId', 'configuratorSettings' => 'optionId'] as $key => $identity) {
            foreach ($payload[$key] ?? [] as $index => $association) {
                foreach ($target[$key] ?? [] as $existing) {
                    if (($existing[$identity] ?? null) === $association[$identity] && isset($existing['id'])) {
                        if (($payload['coverId'] ?? null) === $association['id']) {
                            $payload['coverId'] = $existing['id'];
                        }
                        $payload[$key][$index]['id'] = $existing['id'];
                    }
                }
            }
        }
    }

    public static function hash(array $value): string
    {
        return \App\Integration\CataloguePayloadHash::of($value);
    }
}
