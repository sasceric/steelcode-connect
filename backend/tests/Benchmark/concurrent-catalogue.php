<?php

/**
 * Included by catalogue-scale.php: reuse its seed, Mock HTTP boundary and real
 * exporter. Children have independent database connections, managers and buses.
 * HTTP is simulated. Optional --doctrine-queue uses real Messenger persistence.
 */

use App\Entity\IntegrationConnection;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSelection;
use App\Integration\ImportCancellation;
use App\Integration\IntegrationImportLogger;
use App\Integration\SecretCipher;
use App\Integration\ShopwareCataloguePayload;
use App\Message\ExportShopwareCatalogue;
use App\MessageHandler\ExportShopwareCatalogueHandler;
use App\Service\InventorySyncOutboxService;
use App\Service\ShopwareCatalogueSyncService;
use App\Tests\Benchmark\DoctrineBenchmarkBus;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Uid\Uuid;

require_once __DIR__.'/DoctrineBenchmarkBus.php';

$runConcurrent = function (array $fixtures) use (
    $database,
    $original,
    $manager,
    $dbConfig,
    $counter,
    $bus,
    $client,
    &$worlds,
    &$requests,
    &$writes,
    &$injectedFaults,
): void {
    $parameters = $database->getParams();
    $ormConfiguration = $manager->getConfiguration();
    $events = $manager->getEventManager();
    $manager->clear();
    // Never let forked children share a live PostgreSQL socket or advisory lock.
    $database->close();
    $original->getConnection()->close();
    $directory = sys_get_temp_dir().'/connect-concurrent-'.bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Cannot create isolated benchmark result directory.');
    }
    $children = [];
    $started = microtime(true);
    try {
        foreach ($fixtures as $index => $fixture) {
            $pid = pcntl_fork();
            if ($pid < 0) {
                throw new RuntimeException('Cannot fork benchmark process.');
            }
            if ($pid > 0) {
                $children[$pid] = $index;
                continue;
            }
            $childDatabase = DriverManager::getConnection($parameters, $dbConfig);
            $childManager = new EntityManager($childDatabase, $ormConfiguration, $events);
            $doctrineQueue = in_array('--doctrine-queue', $_SERVER['argv'], true);
            $queueNamespace = 'benchmark_'.str_replace('-', '', $fixture['tenantId']);
            $childBus = $doctrineQueue
                ? new DoctrineBenchmarkBus($childDatabase, $queueNamespace)
                : clone $bus;
            if ($doctrineQueue) {
                $childBus->transport('async');
                $childBus->transport('catalogue');
            }
            $counter->queries = 0;
            $requests = 0;
            $writes = 0;
            // Release other tenants' simulated catalogues to avoid fixture memory
            // being mistaken for actual worker catalogue accumulation.
            $worlds = [$fixture['host'] => $worlds[$fixture['host']]];
            $references = new CatalogueExportReferences($client, new SecretCipher('benchmark'), new ArrayAdapter());
            $builder = new ShopwareCataloguePayload(dirname(__DIR__, 2), $references);
            $makeHandler = static function () use (
                $childManager,
                $client,
                $references,
                $builder,
                $directory,
                $childDatabase,
                &$childBus,
            ): ExportShopwareCatalogueHandler {
                return new ExportShopwareCatalogueHandler(
                    $childManager,
                    $client,
                    $references,
                    new CatalogueExportSelection(),
                    $builder,
                    new IntegrationImportLogger($directory.'/logs'),
                    new ImportCancellation($childDatabase),
                    new InventorySyncOutboxService(),
                    $childBus,
                );
            };
            try {
                $connection = $childManager->find(IntegrationConnection::class, Uuid::fromString($fixture['connectionId']));
                $sync = new ShopwareCatalogueSyncService(
                    $childManager,
                    new CatalogueExportSelection(),
                    $references,
                    $builder,
                    $childBus,
                );
                $plan = $index === 2
                    ? $sync->queue($connection, $childDatabase->fetchFirstColumn(
                        'SELECT id FROM products WHERE tenant_id = :tenant',
                        ['tenant' => $fixture['tenantId']],
                    ))
                    : $sync->queue($connection);
                $handler = $makeHandler();
                $deliveries = 0;
                $replays = 0;
                $chunkTimes = [];
                $slowChunks = [];
                $restartedHandlers = 0;
                while (true) {
                    $worked = false;
                    foreach (['catalogue', 'async'] as $lane) {
                        if (!isset($childBus->lanes[$lane])) {
                            continue;
                        }
                        $envelope = null;
                        $receiver = null;
                        if ($doctrineQueue) {
                            $receiver = $childBus->lanes[$lane];
                            $envelopes = iterator_to_array($receiver->get());
                            if ($envelopes === []) {
                                continue;
                            }
                            $envelope = $envelopes[0];
                            $message = $envelope->getMessage();
                        } else {
                            if ($childBus->lanes[$lane]->isEmpty()) {
                                continue;
                            }
                            $message = $childBus->lanes[$lane]->dequeue();
                        }
                        $worked = true;
                        $phase = $childDatabase->fetchOne(
                            'SELECT work_phase FROM integration_export_plans WHERE tenant_id = :tenant AND id = :plan',
                            ['tenant' => $fixture['tenantId'], 'plan' => $message->planId],
                        );
                        $usageBefore = getrusage();
                        $monotonicStarted = hrtime(true);
                        $chunkStarted = microtime(true);
                        $handler($message);
                        if ($envelope !== null) {
                            $receiver->ack($envelope);
                        }
                        ++$deliveries;
                        // Recreate worker-local handler state at safe boundaries,
                        // replay acknowledged deliveries and deliberately submit
                        // another tenant ID against this plan. Neither may write.
                        if ($deliveries % 50 === 0 || $deliveries === 1) {
                            $before = $writes;
                            if ($doctrineQueue) {
                                // Throw away all in-process queue state. The next
                                // continuation must still be in PostgreSQL.
                                $childBus = new DoctrineBenchmarkBus($childDatabase, $queueNamespace);
                                $childBus->transport('async');
                                $childBus->transport('catalogue');
                            }
                            $handler = $makeHandler();
                            ++$restartedHandlers;
                            $handler($message);
                            $handler(new ExportShopwareCatalogue(
                                (string) Uuid::v7(),
                                $message->connectionId,
                                $message->planId,
                                $message->runId,
                                $message->preview,
                                $message->workToken,
                            ));
                            if ($writes !== $before) {
                                throw new RuntimeException('Acknowledged replay or foreign-tenant delivery wrote remote data.');
                            }
                            ++$replays;
                        }
                        $chunkSeconds = microtime(true) - $chunkStarted;
                        $chunkTimes[] = $chunkSeconds;
                        $usageAfter = getrusage();
                        $cpu = static fn (array $usage): float => $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1000000
                            + $usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1000000;
                        $slowChunks[] = [
                            'delivery' => $deliveries,
                            'phase' => $phase ?: 'initial',
                            'wallSeconds' => round($chunkSeconds, 3),
                            'monotonicSeconds' => round((hrtime(true) - $monotonicStarted) / 1000000000, 3),
                            'phpCpuSeconds' => round($cpu($usageAfter) - $cpu($usageBefore), 3),
                        ];
                        usort($slowChunks, static fn (array $a, array $b): int => $b['wallSeconds'] <=> $a['wallSeconds']);
                        $slowChunks = array_slice($slowChunks, 0, 5);
                        if ($deliveries % 200 === 0) {
                            fwrite(STDERR, sprintf(
                                "Tenant %d: %d deliveries, %.1fs elapsed, %.1fMB memory\n",
                                $index, $deliveries, microtime(true) - $started, memory_get_usage(true) / 1048576,
                            ));
                        }
                    }
                    if (!$worked) {
                        if ($doctrineQueue && $childDatabase->fetchOne(
                            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name IN (:bulk, :catalogue)',
                            ['bulk' => $queueNamespace.'_async', 'catalogue' => $queueNamespace.'_catalogue'],
                        )) {
                            usleep(10000);
                            continue;
                        }
                        break;
                    }
                }
                $outcome = $childDatabase->fetchAssociative(
                    'SELECT status, processed_items, failed_items FROM integration_import_runs WHERE tenant_id = :tenant AND id = :run',
                    ['tenant' => $fixture['tenantId'], 'run' => $plan['runId']],
                );
                $uniquePublished = (int) $childDatabase->fetchOne(
                    'SELECT COUNT(*) FROM integration_export_items i JOIN integration_export_plans p ON p.id = i.plan_id WHERE p.tenant_id = :tenant AND i.plan_id = :plan AND i.status = :status',
                    ['tenant' => $fixture['tenantId'], 'plan' => $plan['planId'], 'status' => 'published'],
                );
                if ($outcome['status'] !== 'completed' || (int) $outcome['processed_items'] !== $fixture['size']
                    || (int) $outcome['failed_items'] !== 0 || $uniquePublished !== $fixture['size']
                    || $writes !== $fixture['size']) {
                    throw new RuntimeException('Concurrent correctness failed: '.json_encode([$outcome, $uniquePublished, $writes]));
                }
                foreach ($worlds[$fixture['host']]['product'] as $product) {
                    if ($product['stock'] !== 47 || $product['active'] !== false
                        || $product['name'] === 'Before update') {
                        throw new RuntimeException('Publication lost content or changed inventory/status.');
                    }
                    if (isset($product['parentId']) && !isset($worlds[$fixture['host']]['product'][$product['parentId']])) {
                        throw new RuntimeException('A variant refers to a foreign or missing parent.');
                    }
                }
                if ((int) $childDatabase->fetchOne('SELECT COUNT(*) FROM inventory_movements WHERE tenant_id = :tenant', [
                    'tenant' => $fixture['tenantId'],
                ]) !== 0) {
                    throw new RuntimeException('Content-only publication changed inventory.');
                }
                if ((int) $childDatabase->fetchOne(
                    'SELECT COUNT(*) FROM product_channel_publications WHERE tenant_id = :tenant AND external_product_id IS NULL',
                    ['tenant' => $fixture['tenantId']],
                ) !== 0) {
                    throw new RuntimeException('Publication failed to retain a stock-sync destination identity.');
                }
                sort($chunkTimes);
                $percentile = static fn (float $percent): float => round($chunkTimes[(int) ceil(count($chunkTimes) * $percent) - 1], 3);
                $seconds = microtime(true) - $started;
                $report = [
                    'products' => $fixture['size'],
                    'variants' => $fixture['variants'],
                    'seconds' => round($seconds, 3),
                    'databaseQueries' => $counter->queries,
                    'httpRequestsSimulated' => $requests,
                    'writesSimulated' => $writes,
                    'uniquePublished' => $uniquePublished,
                    'deliveries' => $deliveries,
                    'acknowledgedReplaysRejected' => $replays,
                    'handlerRecreations' => $restartedHandlers,
                    'queue' => $doctrineQueue ? 'Doctrine Messenger persistence' : 'in-memory',
                    'injectedFaultsRecovered' => array_keys($injectedFaults[$fixture['host']] ?? []),
                    'chunkP95Seconds' => $percentile(0.95),
                    'chunkP99Seconds' => $percentile(0.99),
                    'maxChunkSeconds' => round(max($chunkTimes), 3),
                    'slowestChunks' => $slowChunks,
                    'peakMemoryMB' => round(memory_get_peak_usage(true) / 1048576, 1),
                    'inventoryMovements' => 0,
                ];
                file_put_contents($directory.'/'.$index.'.json', json_encode($report, JSON_THROW_ON_ERROR));
                $childDatabase->close();
                exit(0);
            } catch (Throwable $exception) {
                fwrite(STDERR, 'Concurrent tenant '.$index.' failed: '.$exception->getMessage().PHP_EOL);
                $childDatabase->close();
                exit(1);
            }
        }
        $failed = false;
        foreach ($children as $pid => $index) {
            pcntl_waitpid($pid, $status);
            if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                $failed = true;
            }
        }
        $children = [];
        if ($failed) {
            throw new RuntimeException('One or more concurrent tenant processes failed.');
        }
        $reports = [];
        foreach ($fixtures as $index => $fixture) {
            $reports[] = json_decode(file_get_contents($directory.'/'.$index.'.json'), true, flags: JSON_THROW_ON_ERROR);
            unlink($directory.'/'.$index.'.json');
        }
        echo json_encode([
            'concurrentTenantProcesses' => count($fixtures),
            'seconds' => round(microtime(true) - $started, 3),
            'tenants' => $reports,
            'boundary' => 'Real PostgreSQL/ORM/exporter in concurrent processes; zero-latency simulated Shopware. Queue mode is explicit per tenant. No image uploads, network, actual worker kill or production capacity guarantee.',
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
    } finally {
        // Only benchmark-created children, never the user's servers/workers.
        foreach ($children as $pid => $index) {
            pcntl_waitpid($pid, $status);
        }
    }
};
