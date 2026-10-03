<?php

/** Finish the live recovery acceptance through normal Sales and stock lanes. */

use App\Entity\IntegrationConnection;
use App\Integration\CatalogueExportReferences;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use App\Integration\WooCommerceClient;
use App\Message\SyncShopwareSalesConnection;
use App\Message\SyncWooCommerceSalesConnection;
use App\Service\IntegrationSyncHealth;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/RecoveryKernel.php';

$tenantId = $argv[1] ?? '';
if (!Uuid::isValid($tenantId) || !in_array('--allow-demo-update', $argv, true)) {
    throw new RuntimeException('Explicit tenant and disposable demo approval are required.');
}
$prefix = 'acceptance_recovery_'.str_replace('-', '', (string) Uuid::v7());
$kernel = new RecoveryKernel($prefix);
$kernel->boot();
$container = $kernel->getContainer();
$manager = $container->get('doctrine')->getManager();
$database = $manager->getConnection();
$connections = $manager->getRepository(IntegrationConnection::class)->findBy([
    'tenant' => $tenantId, 'enabled' => true, 'status' => 'active',
]);
$approved = [];
foreach ($connections as $connection) {
    $settings = $connection->getConfiguration()['importSettings'] ?? [];
    if (!in_array($connection->getConnectorKey(), ['shopware', 'woocommerce'], true)) {
        if (($settings['stockAuthority'] ?? null) === 'connect') {
            throw new RuntimeException('An unapproved stock-authoritative connection exists in this tenant.');
        }
        continue;
    }
    $provider = $connection->getConnectorKey();
    $url = rtrim($connection->getConfiguration()['baseUrl'] ?? '', '/');
    if (!in_array([$provider, $url], [
        ['shopware', 'http://shopware67.test'],
        ['woocommerce', 'http://wp-test.test'],
    ], true) || ($settings['salesContinuousSync'] ?? false) !== true) {
        throw new RuntimeException('Both connections must be approved demos with Sales enabled.');
    }
    $approved[] = $connection;
}
if (count($approved) !== 2) {
    throw new RuntimeException('This acceptance expects the two approved demo connections.');
}
$worker = static function (string $lane) use ($prefix): void {
    $process = proc_open([
        PHP_BINARY, __DIR__.'/live-worker-recovery.php', '--worker', $prefix, $lane, '1',
    ], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start the owned recovery worker.');
    }
    echo json_encode(['workerPid' => proc_get_status($process)['pid'], 'lane' => $lane], JSON_THROW_ON_ERROR).PHP_EOL;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Owned worker failed: '.mb_substr($stdout.$stderr, -3000));
    }
};
$bus = $container->get('messenger.default_bus');
foreach ($approved as $connection) {
    $id = (string) $connection->getId();
    $message = $connection->getConnectorKey() === 'shopware'
        ? new SyncShopwareSalesConnection($id)
        : new SyncWooCommerceSalesConnection($tenantId, $id);
    $bus->dispatch($message);
    $worker('sales');
}
$parameters = ['tenant' => $tenantId];
$events = $database->fetchFirstColumn(
    "SELECT product_id FROM inventory_sync_outbox WHERE tenant_id = :tenant AND status IN ('pending', 'failed') ORDER BY product_id",
    $parameters,
);
$inventory = static fn (): array => $database->fetchAllAssociative(
    'SELECT product_id, warehouse_id, quantity, reserved_quantity, unavailable_quantity, incoming_quantity FROM inventory_levels WHERE tenant_id = :tenant ORDER BY product_id, warehouse_id',
    $parameters,
);
$before = $inventory();
$movements = (int) $database->fetchOne('SELECT COUNT(*) FROM inventory_movements WHERE tenant_id = :tenant', $parameters);
if ($events !== []) {
    $bus->dispatch(new RunCommandMessage('app:stock-sync:dispatch --tenant='.$tenantId.' --limit=1000'));
    $worker('stock');
}
if ($database->fetchOne(
    "SELECT COUNT(*) FROM inventory_sync_outbox WHERE tenant_id = :tenant AND status IN ('pending', 'failed')", $parameters,
) !== 0) {
    throw new RuntimeException('Stock events remain pending/failed. Inspect normal inventory exceptions.');
}
if ($inventory() !== $before || (int) $database->fetchOne(
    'SELECT COUNT(*) FROM inventory_movements WHERE tenant_id = :tenant', $parameters,
) !== $movements) {
    throw new RuntimeException('Outbound absolute stock publication altered warehouse balances or movements.');
}
$http = HttpClient::create();
$shopware = new ShopwareClient($http);
$woo = new WooCommerceClient($http);
$references = new CatalogueExportReferences($shopware, new SecretCipher($container->getParameter('kernel.secret')));
$verified = 0;
$unmanaged = 0;
$reports = [];
foreach ($approved as $connection) {
    $provider = $connection->getConnectorKey();
    $stockAuthority = $connection->getConfiguration()['importSettings']['stockAuthority'] ?? null;
    $url = $connection->getConfiguration()['baseUrl'];
    $secrets = $references->credentials($connection, $manager);
    $products = $database->fetchAllAssociative(<<<'SQL'
SELECT DISTINCT p.id, p.parent_id, pub.external_product_id,
    GREATEST(COALESCE((SELECT SUM(l.quantity - l.reserved_quantity - l.unavailable_quantity)
        FROM inventory_levels l JOIN warehouses w ON w.id = l.warehouse_id AND w.tenant_id = l.tenant_id
        WHERE l.tenant_id = p.tenant_id AND l.product_id = p.id AND w.active AND w.fulfillment_enabled), 0), 0) AS available
FROM products p
JOIN product_channel_publications pub ON pub.product_id = p.id AND pub.tenant_id = p.tenant_id
JOIN integration_sales_channels c ON c.id = pub.sales_channel_id AND c.tenant_id = pub.tenant_id
WHERE p.tenant_id = :tenant AND c.connection_id = :connection AND c.active AND pub.visibility > 0
    AND p.id IN (:products)
    AND NOT EXISTS (SELECT 1 FROM products child WHERE child.parent_id = p.id AND child.tenant_id = p.tenant_id)
SQL, $parameters + ['connection' => (string) $connection->getId(), 'products' => $events], ['products' => Doctrine\DBAL\ArrayParameterType::STRING]);
    foreach ($stockAuthority === 'connect' ? $products : [] as $product) {
        $external = $product['external_product_id'];
        if ($provider === 'shopware') {
            $result = $shopware->searchPage($url, $secrets, 'product', ['ids' => [$external], 'limit' => 1]);
            $remote = $result['data'][0] ?? null;
            $quantity = $remote['attributes']['stock'] ?? $remote['stock'] ?? null;
        } else {
            $resource = 'products/'.$external;
            if ($product['parent_id'] !== null) {
                $parent = $database->fetchOne(
                    "SELECT external_id FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = 'product' AND local_id = :parent",
                    $parameters + ['connection' => (string) $connection->getId(), 'parent' => $product['parent_id']],
                );
                $resource = 'products/'.$parent.'/variations/'.$external;
            }
            $remote = $woo->object('GET', $url, $secrets, $resource);
            if (($remote['manage_stock'] ?? false) === false) {
                ++$unmanaged;
                continue;
            }
            $quantity = $remote['stock_quantity'] ?? null;
        }
        if ($quantity === null || (float) $quantity !== (float) $product['available']) {
            throw new RuntimeException('Live leaf stock does not equal the tenant warehouse aggregate.');
        }
        ++$verified;
    }
    $health = (new IntegrationSyncHealth())->read($database, $tenantId, (string) $connection->getId());
    if ($health['health'] !== 'ok') {
        throw new RuntimeException('Connection health requires attention: '.json_encode($health['alerts']));
    }
    $reports[$provider] = ['stockAuthority' => $stockAuthority, 'stockReadbackRequired' => $stockAuthority === 'connect'] + $health;
}
if ((int) $database->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name LIKE :prefix', ['prefix' => $prefix.'_%']) !== 0) {
    throw new RuntimeException('An owned queue still contains messages; preserve it for inspection.');
}
echo json_encode([
    'outboxEventsProcessed' => count($events),
    'liveManagedLeafStocksVerified' => $verified,
    'intentionallyUnmanagedWooLeaves' => $unmanaged,
    'unchangedWarehouseBalancesAndMovements' => true,
    'health' => $reports,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
$kernel->shutdown();
