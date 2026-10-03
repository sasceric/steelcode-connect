<?php

/**
 * Real Shopware/Woo HTTP and actual Messenger process recycling on demo data.
 * No application worker, browser, frontend or API is stopped. Only unique queues
 * created here are consumed. Settings are restored; history remains inspectable.
 * Usage: php tests/Acceptance/live-worker-recovery.php TENANT CONNECTION --allow-demo-update
 * This checks graceful message-boundary recycling, NOT arbitrary SIGKILL recovery.
 */

use App\Entity\IntegrationConnection;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSelection;
use App\Integration\CatalogueExportSettings;
use App\Integration\ShopwareClient;
use App\Integration\WooCommerceClient;
use App\Message\ExportShopwareCatalogue;
use App\Message\ExportWooCommerceCatalogue;
use App\Message\SyncShopwareSalesConnection;
use App\Message\SyncWooCommerceSalesConnection;
use App\Service\CatalogueSyncService;
use App\Service\IntegrationSyncHealth;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/RecoveryKernel.php';

if (($argv[1] ?? '') === '--worker') {
    $kernel = new RecoveryKernel($argv[2] ?? '');
    $application = new Application($kernel);
    $application->setAutoExit(false);
    exit($application->run(new ArrayInput([
        'command' => 'messenger:consume',
        'receivers' => [$argv[3] ?? 'async'],
        '--limit' => $argv[4] ?? '1',
        '--time-limit' => '50',
        '--sleep' => '0.1',
        '--memory-limit' => '256M',
        '--no-interaction' => true,
    ])));
}

[$script, $tenantId, $connectionId] = array_pad($argv, 3, '');
if (!Uuid::isValid($tenantId) || !Uuid::isValid($connectionId)
    || !in_array('--allow-demo-update', $argv, true)) {
    throw new RuntimeException('Explicit tenant, connection and disposable demo approval are required.');
}
$prefix = 'acceptance_recovery_'.str_replace('-', '', (string) Uuid::v7());
$kernel = new RecoveryKernel($prefix);
$kernel->boot();
$container = $kernel->getContainer();
$manager = $container->get('doctrine')->getManager();
$database = $manager->getConnection();
$parameters = ['tenant' => $tenantId, 'connection' => $connectionId];
$connection = $manager->getRepository(IntegrationConnection::class)->findOneBy([
    'tenant' => $tenantId,
    'id' => $connectionId,
]);
if (!$connection instanceof IntegrationConnection) {
    throw new RuntimeException('Connection does not belong to the specified tenant.');
}
$provider = $connection->getConnectorKey();
$url = rtrim($connection->getConfiguration()['baseUrl'] ?? '', '/');
if (!in_array([$provider, $url], [
    ['shopware', 'http://shopware67.test'],
    ['woocommerce', 'http://wp-test.test'],
], true)) {
    throw new RuntimeException('Only the explicitly approved local demo shops are allowed.');
}
$savedConfiguration = $connection->getConfiguration();
$saved = CatalogueExportSettings::normalize($savedConfiguration['exportSettings'] ?? [], $provider);
$roots = $database->fetchAllAssociative(<<<'SQL'
SELECT p.id, COUNT(child.id) AS variants
FROM products p
JOIN integration_entity_mappings m ON m.local_id = p.id AND m.tenant_id = p.tenant_id
    AND m.connection_id = :connection AND m.entity_type = 'product'
LEFT JOIN products child ON child.parent_id = p.id AND child.tenant_id = p.tenant_id
WHERE p.tenant_id = :tenant AND p.parent_id IS NULL
GROUP BY p.id HAVING COUNT(child.id) BETWEEN 1 AND 5
ORDER BY p.id LIMIT 2
SQL, $parameters);
$simple = $database->fetchFirstColumn(<<<'SQL'
SELECT p.id FROM products p
JOIN integration_entity_mappings m ON m.local_id = p.id AND m.tenant_id = p.tenant_id
    AND m.connection_id = :connection AND m.entity_type = 'product'
WHERE p.tenant_id = :tenant AND p.parent_id IS NULL
    AND NOT EXISTS (SELECT 1 FROM products c WHERE c.tenant_id = p.tenant_id AND c.parent_id = p.id)
ORDER BY p.id LIMIT 30
SQL, $parameters);
if (count($roots) !== 2 || count($simple) !== 30) {
    throw new RuntimeException('Two mapped variable parents and thirty simple products are required.');
}
$settings = array_replace($saved, [
    'scope' => 'selected',
    'productIds' => array_merge(array_column($roots, 'id'), $simple),
    'categoryIds' => [],
    'brandIds' => [],
    'manufacturerIds' => [],
    'excludeIds' => [],
    'includeVariants' => true,
    'fields' => ['content'],
    'publicationMode' => 'keep',
    'automaticSync' => false,
    'createMissingReferences' => false,
]);
$selected = iterator_to_array((new CatalogueExportSelection())->selectedIds($database, $connection, $settings), false);
$references = new CatalogueExportReferences(
    new ShopwareClient(HttpClient::create()),
    new App\Integration\SecretCipher($container->getParameter('kernel.secret')),
);
$secrets = $references->credentials($connection, $manager);
$http = HttpClient::create();
$woo = new WooCommerceClient($http);
$shopware = new ShopwareClient($http);
$snapshot = static function () use ($database, $parameters, $selected, $provider, $url, $secrets, $woo, $shopware): array {
    $mappings = $database->fetchAllAssociative(
        "SELECT local_id, external_id FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = 'product' AND local_id IN (:ids) ORDER BY local_id, external_id",
        $parameters + ['ids' => $selected],
        ['ids' => ArrayParameterType::STRING],
    );
    if (count($mappings) !== count($selected) || count(array_unique(array_column($mappings, 'local_id'))) !== count($selected)) {
        throw new RuntimeException('Every selected product requires one unambiguous mapping.');
    }
    $ids = array_column($mappings, 'external_id');
    $remote = [];
    if ($provider === 'shopware') {
        $result = $shopware->searchPage($url, $secrets, 'product', ['ids' => $ids, 'limit' => 100]);
        foreach ($result['data'] ?? [] as $item) {
            $value = $item['attributes'] ?? $item;
            $remote[$item['id']] = [
                'images' => $value['coverId'] ?? null,
                'stock' => $value['stock'] ?? null,
                'status' => $value['active'] ?? null,
                'parent' => $value['parentId'] ?? null,
            ];
        }
    } else {
        $result = $woo->page($url, $secrets, 'products', [
            'include' => implode(',', $ids), 'per_page' => 100, 'context' => 'edit',
        ]);
        foreach ($result['items'] as $item) {
            $remote[(string) $item['id']] = [
                'images' => array_column($item['images'] ?? [], 'id'),
                'stock' => $item['stock_quantity'],
                'status' => $item['status'],
                'parent' => null,
            ];
            if ($item['type'] !== 'variable') {
                continue;
            }
            $variants = $woo->page($url, $secrets, 'products/'.$item['id'].'/variations', [
                'per_page' => 100, 'context' => 'edit',
            ]);
            foreach ($variants['items'] as $variant) {
                if (in_array((string) $variant['id'], $ids, true)) {
                    $remote[(string) $variant['id']] = [
                        'images' => isset($variant['image']['id']) ? [$variant['image']['id']] : [],
                        'stock' => $variant['stock_quantity'],
                        'status' => $variant['status'],
                        'parent' => (string) $item['id'],
                    ];
                }
            }
        }
    }
    ksort($remote);
    if (count($remote) !== count($selected)) {
        throw new RuntimeException('A mapped destination is missing from the live shop.');
    }

    return ['mappings' => $mappings, 'remote' => $remote];
};
$before = $snapshot();
$inventoryBefore = $database->fetchAllAssociative(
    'SELECT product_id, warehouse_id, quantity, reserved_quantity FROM inventory_levels WHERE tenant_id = :tenant ORDER BY product_id, warehouse_id',
    ['tenant' => $tenantId],
);
$movementsBefore = (int) $database->fetchOne('SELECT COUNT(*) FROM inventory_movements WHERE tenant_id = :tenant', ['tenant' => $tenantId]);
$workerPids = [];
$worker = static function (string $lane = 'async') use ($prefix, &$workerPids): void {
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--worker', $prefix, $lane, '1'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2),
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the test-owned Messenger worker.');
    }
    $pid = proc_get_status($process)['pid'];
    $workerPids[] = $pid;
    echo json_encode(['workerPid' => $pid, 'lane' => $lane, 'stopAfterMessages' => 1], JSON_THROW_ON_ERROR).PHP_EOL;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException('Owned worker failed: '.$exit.' '.mb_substr($stdout.$stderr, -3000));
    }
};
$report = ['provider' => $provider, 'selected' => count($selected), 'simple' => 30, 'parents' => 2];
$runId = null;
try {
    $connection->updateConfiguration(array_replace($savedConfiguration, ['exportSettings' => $settings]));
    $manager->flush();
    $queued = $container->get(CatalogueSyncService::class)->queue($connection);
    $runId = $queued['runId'];
    $scope = $parameters + ['run' => $runId, 'plan' => $queued['planId']];
    echo json_encode($report + ['run' => $runId, 'ownedQueue' => $prefix.'_async'], JSON_THROW_ON_ERROR).PHP_EOL;
    $partialRestart = false;
    for ($chunk = 0; $chunk < 30; ++$chunk) {
        $pending = (int) $database->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue', ['queue' => $prefix.'_async']);
        if ($pending < 1) {
            throw new RuntimeException('The incomplete run has no durable continuation.');
        }
        $worker();
        $run = $database->fetchAssociative(
            'SELECT status, processed_items, total_items, created_items, updated_items, failed_items FROM integration_import_runs WHERE id = :run AND tenant_id = :tenant AND connection_id = :connection',
            ['run' => $runId] + $parameters,
        );
        $items = $database->fetchAssociative(
            "SELECT COUNT(*) FILTER (WHERE i.status = 'published') AS published, COUNT(*) FILTER (WHERE i.status = 'pending') AS pending FROM integration_export_items i JOIN integration_export_plans p ON p.id = i.plan_id WHERE p.id = :plan AND p.tenant_id = :tenant AND p.connection_id = :connection AND p.run_id = :run",
            $scope,
        );
        echo json_encode(['chunk' => $chunk, 'run' => $run, 'items' => $items], JSON_THROW_ON_ERROR).PHP_EOL;
        if ((int) $items['published'] > 0 && (int) $items['pending'] > 0) {
            if ($database->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue', ['queue' => $prefix.'_async']) < 1) {
                throw new RuntimeException('No persisted message survived the worker exit mid-publication.');
            }
            $partialRestart = true;
        }
        if (!in_array($run['status'], ['queued', 'running'], true)) {
            break;
        }
    }
    if (!$partialRestart || $run['status'] !== 'completed' || (int) $run['created_items'] !== 0
        || (int) $run['failed_items'] !== 0 || (int) $run['processed_items'] !== count($selected)
        || (int) $items['published'] !== count($selected)) {
        throw new RuntimeException('Expected exact completed updates and a real mid-publication process restart.');
    }
    if ($snapshot() !== $before) {
        throw new RuntimeException('Mapped IDs, images, stock, status or parent identity changed.');
    }
    // Re-deliver the acknowledged initial message through an actual worker.
    $class = $provider === 'shopware' ? ExportShopwareCatalogue::class : ExportWooCommerceCatalogue::class;
    $container->get('messenger.transport.async')->send(new Envelope(new $class(
        $tenantId, $connectionId, $queued['planId'], $runId, true,
    )));
    $worker();
    $afterReplay = $database->fetchAssociative(
        'SELECT status, processed_items, total_items, created_items, updated_items, failed_items FROM integration_import_runs WHERE id = :run AND tenant_id = :tenant AND connection_id = :connection',
        ['run' => $runId] + $parameters,
    );
    if ($afterReplay !== $run || $snapshot() !== $before) {
        throw new RuntimeException('An acknowledged message replay modified the completed publication.');
    }
    $inventoryAfter = $database->fetchAllAssociative(
        'SELECT product_id, warehouse_id, quantity, reserved_quantity FROM inventory_levels WHERE tenant_id = :tenant ORDER BY product_id, warehouse_id',
        ['tenant' => $tenantId],
    );
    if ($inventoryAfter !== $inventoryBefore || $movementsBefore !== (int) $database->fetchOne(
        'SELECT COUNT(*) FROM inventory_movements WHERE tenant_id = :tenant', ['tenant' => $tenantId],
    )) {
        throw new RuntimeException('Content-only publication modified warehouse balances or movements.');
    }
    $report += [
        'runId' => $runId,
        'realWorkerProcesses' => count($workerPids),
        'distinctWorkerPids' => count(array_unique($workerPids)),
        'gracefulRestartMidPublication' => true,
        'completedUpdates' => (int) $run['updated_items'],
        'acknowledgedReplayRejected' => true,
        'unchangedIdentitiesImagesStockStatusParents' => true,
        'unchangedWarehouseBalancesAndMovements' => true,
    ];
    // Refresh the existing, tenant-resolved Sales workflow; no new test order.
    if (($savedConfiguration['importSettings']['salesContinuousSync'] ?? false) === true) {
        $salesMessage = $provider === 'shopware'
            ? new SyncShopwareSalesConnection($connectionId)
            : new SyncWooCommerceSalesConnection($tenantId, $connectionId);
        $container->get('messenger.default_bus')->dispatch($salesMessage);
        $worker('sales');
        $report['salesPolledAfterRestart'] = true;
    }
} finally {
    // Restore only export settings, preserving any unrelated concurrent settings.
    $current = json_decode($database->fetchOne(
        'SELECT configuration FROM integration_connections WHERE tenant_id = :tenant AND id = :connection', $parameters,
    ), true, flags: JSON_THROW_ON_ERROR);
    if (array_key_exists('exportSettings', $savedConfiguration)) {
        $current['exportSettings'] = $savedConfiguration['exportSettings'];
    } else {
        unset($current['exportSettings']);
    }
    $database->update('integration_connections', ['configuration' => json_encode($current, JSON_THROW_ON_ERROR)], [
        'tenant_id' => $tenantId, 'id' => $connectionId,
    ]);
    echo "Original export settings restored; application services left running.\n";
}
$remaining = (int) $database->fetchOne(
    'SELECT COUNT(*) FROM messenger_messages WHERE queue_name LIKE :prefix', ['prefix' => $prefix.'_%'],
);
if ($remaining !== 0) {
    throw new RuntimeException('Owned queues still contain messages; retain them for inspection.');
}
$report['health'] = (new IntegrationSyncHealth())->read($database, $tenantId, $connectionId);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
$kernel->shutdown();
