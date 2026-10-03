<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSalesChannel;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/** Durable, bounded export phases shared by every catalogue provider. */
final class CatalogueExportWorkflow
{
    private string $provider = '';

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly CatalogueExportReferences $references,
        private readonly CatalogueExportSelection $selection,
        private readonly IntegrationImportLogger $logger,
        private readonly ImportCancellation $cancellation,
        private readonly MessageBusInterface $bus,
    )
    {
    }

    /** Hooks translate provider APIs; ownership, phases and queue state stay here. */
    public function handle(object $message, string $provider, array $hooks): void
    {
        foreach ([$message->tenantId, $message->connectionId, $message->planId, $message->runId] as $id) {
            if (!Uuid::isValid($id)) {
                return;
            }
        }
        $this->provider = $provider;
        $database = $this->manager->getConnection();
        $key = 'catalogue-export:'.$message->tenantId.':'.$message->connectionId;
        if (!$database->fetchOne('SELECT pg_try_advisory_lock(hashtextextended(:key, 0))', ['key' => $key])) {
            throw new RecoverableMessageHandlingException('Another catalogue publication is running.');
        }
        try {
            $connection = $this->manager->getRepository(IntegrationConnection::class)->findOneBy([
                'id' => Uuid::fromString($message->connectionId),
                'tenant' => Uuid::fromString($message->tenantId),
            ]);
            if (!$connection instanceof IntegrationConnection || $connection->getConnectorKey() !== $provider) {
                return;
            }
            $run = $this->manager->getRepository(IntegrationImportRun::class)->findOneBy([
                'id' => Uuid::fromString($message->runId),
                'tenant' => $connection->getTenant(),
                'connection' => $connection,
            ]);
            $plan = $database->fetchAssociative(
                'SELECT * FROM integration_export_plans WHERE id = :id AND tenant_id = :tenant AND connection_id = :connection AND run_id = :run',
                ['id' => $message->planId, 'tenant' => $message->tenantId, 'connection' => $message->connectionId, 'run' => $message->runId],
            );
            if (!$run instanceof IntegrationImportRun || !$plan || !in_array($run->getStatus(), ['queued', 'running'], true)) {
                return;
            }
            if (($run->getStatus() === 'running' || $plan['work_phase'] !== null)
                && ($plan['work_token'] ?? null) !== $message->workToken) {
                return;
            }
            try {
                $this->cancellation->throwIfCancelled($run->getId());
                $settings = json_decode($plan['settings'], true, flags: JSON_THROW_ON_ERROR);
                $url = (string) ($connection->getConfiguration()['baseUrl'] ?? '');
                $this->assertCurrentConfiguration($message, $plan['settings_hash'], $url);
                if ($plan['sync_mode'] === 'automatic' && !($settings['automaticSync'] ?? false)) {
                    throw new \DomainException('Automatic catalogue synchronization is disabled.');
                }
                $secrets = $this->references->credentials($connection, $this->manager);
                $this->executeChunk($message, $connection, $run, $plan, $settings, $url, $secrets, $hooks);
            } catch (ImportCancelledException) {
                $database->update('integration_export_plans', ['status' => 'cancelled'], ['id' => $message->planId, 'tenant_id' => $message->tenantId]);
            } catch (\Throwable $exception) {
                $current = $database->fetchAssociative(
                    'SELECT work_phase, selection_cursor, retry_count FROM integration_export_plans WHERE id = :id AND tenant_id = :tenant',
                    ['id' => $message->planId, 'tenant' => $message->tenantId],
                );
                if (($exception instanceof IntegrationRetryLaterException || $exception instanceof TransportExceptionInterface)
                    && (int) $current['retry_count'] < 5) {
                    $attempt = (int) $current['retry_count'] + 1;
                    $delay = max(
                        $exception instanceof IntegrationRetryLaterException ? $exception->delaySeconds : 30,
                        min(300, 5 * (2 ** $attempt)),
                    );
                    $database->update('integration_export_plans', ['retry_count' => $attempt], ['id' => $message->planId, 'tenant_id' => $message->tenantId]);
                    $this->manager->flush();
                    $this->checkpoint($message, $current['work_phase'] ?? ($message->preview ? 'preview' : 'preflight'), $current['selection_cursor'], $plan['sync_mode'], $delay);

                    return;
                }
                $run->fail($exception->getMessage());
                $database->update('integration_export_plans', ['status' => 'failed'], ['id' => $message->planId, 'tenant_id' => $message->tenantId]);
                $this->logger->error($run, 'failed', 'integrationLog.exportFailed', ['reason' => $exception->getMessage()]);
                $this->manager->flush();
            }
        } finally {
            $database->executeQuery('SELECT pg_advisory_unlock(hashtextextended(:key, 0))', ['key' => $key]);
            $this->manager->clear();
        }
    }

    private static function hash(array $value): string
    {
        return \App\Integration\CataloguePayloadHash::of($value);
    }

    /** One durable phase/chunk per delivery; no remote transaction spans deliveries. */
    private function executeChunk(
        object $message,
        IntegrationConnection $connection,
        IntegrationImportRun $run,
        array $plan,
        array $settings,
        string $url,
        array $secrets,
        array $hooks,
    ): void
    {
        $database = $this->manager->getConnection();
        $hash = $plan['settings_hash'];
        $this->assertCurrentConfiguration($message, $hash, $url);
        $phase = $plan['work_phase'];
        if ($run->getStatus() === 'queued' || $phase === null) {
            $preview = $message->preview;
            if (!$preview && ($settings['_destinationUrl'] ?? null) !== $url) {
                throw new \DomainException('The destination endpoint changed. Build a new preview.');
            }
            $settings['_destinationUrl'] = $url;
            $channel = ($hooks['destination'])($connection, $settings, $url, $secrets);
            if (!$channel || !($channel['active'] ?? false)) {
                throw new \DomainException('The destination sales channel is missing or inactive.');
            }
            if ($preview) {
                $settings = $this->references->standardMappings($connection, $this->manager, $settings);
            } else {
                $database->executeStatement(
                    'UPDATE integration_export_items SET preflight_checked = FALSE WHERE plan_id = :plan',
                    ['plan' => $message->planId],
                );
            }
            $phase = $preview ? 'preview' : 'preflight';
            $plan['selection_cursor'] = null;
            $database->update('integration_export_plans', [
                'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                'work_phase' => $phase,
                'selection_cursor' => null,
                'work_token' => $message->workToken ?? null,
                'retry_count' => 0,
            ], ['id' => $message->planId, 'tenant_id' => $message->tenantId]);
            $run->start(0, $preview ? 'exportPreview' : 'exportPreflight');
            $this->manager->flush();
        } elseif (($settings['_destinationUrl'] ?? null) !== $url) {
            throw new \DomainException('The destination endpoint changed. Build a new preview.');
        }
        $this->initializeCounts($message, $run, $settings, $phase === 'preview');
        if ($phase === 'preview') {
            $selection = $settings;
            if ($plan['product_ids'] !== null) {
                $selection['scope'] = 'selected';
                foreach (['categoryIds', 'brandIds', 'manufacturerIds'] as $key) {
                    $selection[$key] = [];
                }
                $selection['productIds'] = json_decode($plan['product_ids'], true, flags: JSON_THROW_ON_ERROR);
                $selection['includeVariants'] = false;
            }
            if ($run->getTotalItems() === 0) {
                $run->updateTotalItems($this->selection->count($database, $connection, $selection));
            }
            $this->manager->flush();
            $ids = $this->selection->page($database, $connection, $selection, $plan['selection_cursor'], 25);
            $existing = $database->fetchFirstColumn(
                'SELECT product_id FROM integration_export_items WHERE plan_id = :plan AND product_id IN (:ids)',
                ['plan' => $message->planId, 'ids' => $ids],
                ['ids' => ArrayParameterType::STRING],
            );
            $remaining = array_values(array_diff($ids, $existing));
            if ($remaining !== []) {
                $finished = ($hooks['preview'])(
                    $message,
                    $connection,
                    $run,
                    $settings,
                    $url,
                    $secrets,
                    $remaining,
                    microtime(true) + 10,
                );
                if (!$finished) {
                    // Keep this page's cursor until every item is frozen. Already
                    // stored rows are skipped when the next delivery resumes it.
                    $this->checkpoint($message, 'preview', $plan['selection_cursor'], $plan['sync_mode']);

                    return;
                }
            }
            if (count($ids) === 25) {
                $this->checkpoint($message, 'preview', end($ids), $plan['sync_mode']);

                return;
            }
            if ($run->getProcessedItems() === 0) {
                throw new \DomainException('No products match this selection.');
            }
            if ($plan['sync_mode'] === null || $run->getFailedItems() > 0) {
                $this->finish($message, $run, $run->getFailedItems() ? 'blocked' : 'ready');

                return;
            }
            $database->update('integration_export_plans', ['status' => 'publishing'], ['id' => $message->planId, 'tenant_id' => $message->tenantId]);
            $this->initializeCounts($message, $run, $settings, false);
            $run->setCurrentStage('exportPreflight');
            $this->manager->flush();
            $this->checkpoint($message, 'preflight', null, $plan['sync_mode']);

            return;
        }
        if ($phase === 'preflight') {
            $run->setCurrentStage('exportPreflight');
            $deadline = microtime(true) + 10;
            $rows = $database->fetchAllAssociative(
                'SELECT * FROM integration_export_items WHERE plan_id = :plan AND preflight_checked = FALSE AND status <> :published ORDER BY parent_id NULLS FIRST, product_id LIMIT 25',
                ['plan' => $message->planId, 'published' => 'published'],
            );
            foreach ($rows as $item) {
                $this->cancellation->throwIfCancelled($run->getId());
                $this->assertCurrentConfiguration($message, $hash, $url);
                $product = $this->manager->getRepository(Product::class)->findOneBy([
                    'tenant' => $connection->getTenant(),
                    'id' => $item['product_id'],
                ]);
                if (!$product instanceof Product
                    || self::hash(($hooks['build'])(
                        $product,
                        $connection,
                        $this->manager,
                        $settings,
                        $item['action'] === 'create',
                    )) !== $item['source_hash']) {
                    throw new \DomainException('Catalogue data changed after preview. Build a new preview.');
                }
                $database->update('integration_export_items', ['preflight_checked' => true], ['id' => $item['id'], 'plan_id' => $message->planId]);
                $this->manager->detach($product);
                $this->trimManagedEntities();
                if (microtime(true) >= $deadline) {
                    break;
                }
            }
            $this->manager->flush();
            $more = $database->fetchOne(
                'SELECT 1 FROM integration_export_items WHERE plan_id = :plan AND preflight_checked = FALSE AND status <> :published LIMIT 1',
                ['plan' => $message->planId, 'published' => 'published'],
            );
            $this->checkpoint($message, $more ? 'preflight' : 'publish', null, $plan['sync_mode']);

            return;
        }
        $channel = ($hooks['destination'])($connection, $settings, $url, $secrets);
        if (!$channel || !($channel['active'] ?? false)) {
            throw new \DomainException('The destination sales channel is missing or inactive.');
        }
        ($hooks['publish'])($message, $connection, $run, $settings, $url, $secrets, $channel, true);
        if ($database->fetchOne("SELECT 1 FROM integration_export_items WHERE plan_id = :plan AND status = 'pending' LIMIT 1", ['plan' => $message->planId])) {
            $this->checkpoint($message, 'publish', null, $plan['sync_mode']);

            return;
        }
        $this->finish($message, $run, $run->getFailedItems() ? 'failed' : 'completed');
    }

    /** Repair older non-atomic runs once; subsequent chunks use durable counters. */
    private function initializeCounts(
        object $message,
        IntegrationImportRun $run,
        array &$settings,
        bool $preview,
    ): void
    {
        $phase = $preview ? 'preview' : 'publication';
        if (($settings['_atomicCountsPhase'] ?? null) === $phase) {
            return;
        }
        $database = $this->manager->getConnection();
        $database->transactional(function () use ($message, $run, &$settings, $preview, $phase): void {
            $this->syncCounts($message, $run, $preview);
            $settings['_atomicCountsPhase'] = $phase;
            $this->manager->getConnection()->update('integration_export_plans', [
                'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
            ], ['id' => $message->planId, 'tenant_id' => $message->tenantId]);
        });
    }

    private function syncCounts(
        object $message,
        IntegrationImportRun $run,
        bool $preview,
    ): void
    {
        self::repairCounts($this->manager, $message, $run, $preview);
    }

    public static function repairCounts(
        EntityManagerInterface $manager,
        object $message,
        IntegrationImportRun $run,
        bool $preview,
    ): void
    {
        $condition = $preview ? "issues::jsonb = '[]'::jsonb" : "status = 'published'";
        $failed = $preview ? "issues::jsonb <> '[]'::jsonb" : "status = 'failed'";
        $manager->getConnection()->executeStatement(<<<SQL
UPDATE integration_import_runs SET
    processed_items = c.created + c.updated + c.failed, created_items = c.created,
    updated_items = c.updated, failed_items = c.failed, total_items = GREATEST(total_items, c.total)
FROM (SELECT COUNT(*) AS total,
    COUNT(*) FILTER (WHERE $condition AND action = 'create') AS created,
    COUNT(*) FILTER (WHERE $condition AND action = 'update') AS updated,
    COUNT(*) FILTER (WHERE $failed) AS failed FROM integration_export_items WHERE plan_id = :plan) c
WHERE id = :run AND tenant_id = :tenant
SQL, ['plan' => $message->planId, 'run' => $message->runId, 'tenant' => $message->tenantId]);
        $manager->refresh($run);
    }


    private function checkpoint(
        object $message,
        string $phase,
        ?string $cursor,
        ?string $mode,
        int $delay = 0,
    ): void
    {
        $database = $this->manager->getConnection();
        $database->beginTransaction();
        try {
            $token = (string) Uuid::v7();
            $database->update('integration_export_plans', [
                'work_phase' => $phase,
                'selection_cursor' => $cursor,
                'work_token' => $token,
            ], ['id' => $message->planId, 'tenant_id' => $message->tenantId]);
            if ($delay === 0) {
                $database->update('integration_export_plans', ['retry_count' => 0], ['id' => $message->planId, 'tenant_id' => $message->tenantId]);
            }
            $stamps = [new TransportNamesStamp([$mode === 'automatic' ? 'catalogue' : 'async'])];
            if ($delay > 0) {
                $stamps[] = new DelayStamp($delay * 1000);
            }
            $class = $message::class;
            $this->bus->dispatch(new $class(
                $message->tenantId,
                $message->connectionId,
                $message->planId,
                $message->runId,
                $message->preview,
                $token,
            ), $stamps);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }
    }

    private function finish(
        object $message,
        IntegrationImportRun $run,
        string $status,
    ): void
    {
        $this->cancellation->throwIfCancelled($run->getId());
        $run->complete();
        $this->manager->getConnection()->update(
            'integration_export_plans',
            ['status' => $status, 'work_phase' => 'done'],
            ['id' => $message->planId, 'tenant_id' => $message->tenantId],
        );
        $this->logger->info($run, 'completed', 'integrationLog.exportCompleted', [
            'createdItems' => $run->getCreatedItems(),
            'updatedItems' => $run->getUpdatedItems(),
            'failedItems' => $run->getFailedItems(),
        ]);
        $this->manager->flush();
    }

    private function assertCurrentConfiguration(
        object $message,
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
            || CatalogueExportSettings::hash($configuration['exportSettings'] ?? [], $this->provider) !== $hash) {
            throw new \DomainException('The connection or export configuration changed. Publication stopped; build a new preview.');
        }
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

}
