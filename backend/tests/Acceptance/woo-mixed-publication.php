<?php

/**
 * Disposable wp-test.test acceptance only. Requires an authenticated Connect
 * cookie jar and running async worker. Publishes existing mapped catalogue
 * content through the normal queue, verifies IDs/images/stock, restores scope.
 * Usage: php tests/Acceptance/woo-mixed-publication.php TENANT CONNECTION COOKIE_JAR --allow-demo-update
 * This is not a production load test or a fresh image-upload benchmark.
 */

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSecret;
use App\Integration\CatalogueExportSelection;
use App\Integration\SecretCipher;
use App\Integration\WooCommerceClient;
use App\Kernel;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__).'/bootstrap.php';

[$script, $tenantId, $connectionId, $cookieJar] = array_pad($argv, 4, '');
if (!Uuid::isValid($tenantId) || !Uuid::isValid($connectionId)
    || !is_file($cookieJar) || !in_array('--allow-demo-update', $argv, true)) {
    throw new RuntimeException('Explicit demo approval, tenant, connection and cookie jar are required.');
}
$kernel = new Kernel('dev', false);
$kernel->boot();
$manager = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $manager->getRepository(IntegrationConnection::class)->findOneBy([
    'tenant' => $tenantId,
    'id' => $connectionId,
    'connectorKey' => 'woocommerce',
]);
if (!$connection instanceof IntegrationConnection
    || ($connection->getConfiguration()['baseUrl'] ?? '') !== 'http://wp-test.test') {
    throw new RuntimeException('Only the explicitly identified disposable wp-test.test connection is permitted.');
}
$api = static function (string $method, string $path, ?array $body = null) use ($cookieJar, $connectionId): array {
    $curl = curl_init('http://localhost:8000/api/v1/integrations/'.$connectionId.'/exports/'.$path);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Connect acceptance API returned HTTP '.$status.' for '.$path);
    }

    return json_decode($response, true, flags: JSON_THROW_ON_ERROR);
};
$saved = $api('GET', 'configuration')['settings'];
$database = $manager->getConnection();
$parameters = ['tenant' => $tenantId, 'connection' => $connectionId];
$roots = $database->fetchAllAssociative(<<<'SQL'
SELECT p.id, COUNT(child.id) AS variants
FROM products p
JOIN integration_entity_mappings mapping
  ON mapping.local_id = p.id AND mapping.tenant_id = p.tenant_id
  AND mapping.connection_id = :connection AND mapping.entity_type = 'product'
LEFT JOIN products child ON child.parent_id = p.id AND child.tenant_id = p.tenant_id
WHERE p.tenant_id = :tenant AND p.parent_id IS NULL
GROUP BY p.id
HAVING COUNT(child.id) BETWEEN 1 AND 5
ORDER BY p.id LIMIT 10
SQL, $parameters);
$simple = $database->fetchFirstColumn(<<<'SQL'
SELECT p.id FROM products p
JOIN integration_entity_mappings mapping
  ON mapping.local_id = p.id AND mapping.tenant_id = p.tenant_id
  AND mapping.connection_id = :connection AND mapping.entity_type = 'product'
WHERE p.tenant_id = :tenant AND p.parent_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM products child WHERE child.tenant_id = p.tenant_id AND child.parent_id = p.id)
ORDER BY p.id LIMIT 90
SQL, $parameters);
if (count($roots) !== 10 || count($simple) !== 90) {
    throw new RuntimeException('The acceptance requires 10 small variable parents and 90 simple products.');
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
$cipher = new SecretCipher($kernel->getContainer()->getParameter('kernel.secret'));
$secrets = [];
foreach ($manager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
    $secrets[$secret->getSecretKey()] = $cipher->decrypt($secret->getCiphertext(), $secret->getNonce());
}
$client = new WooCommerceClient(HttpClient::create());
$snapshot = static function () use ($database, $parameters, $selected, $client, $secrets): array {
    $mappings = $database->fetchAllAssociative(
        "SELECT local_id, external_id FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = 'product' AND local_id IN (:ids)",
        $parameters + ['ids' => $selected],
        ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING],
    );
    $ids = array_column($mappings, 'external_id');
    sort($ids);
    $remote = [];
    foreach (array_chunk($ids, 100) as $chunk) {
        $page = $client->page('http://wp-test.test', $secrets, 'products', [
            'include' => implode(',', $chunk), 'per_page' => 100, 'context' => 'edit',
        ]);
        foreach ($page['items'] as $item) {
            $remote[(string) $item['id']] = [
                'images' => array_column($item['images'] ?? [], 'id'),
                'stock' => $item['stock_quantity'],
                'status' => $item['status'],
            ];
            if ($item['type'] === 'variable') {
                $variants = $client->page('http://wp-test.test', $secrets, 'products/'.$item['id'].'/variations', ['per_page' => 100, 'context' => 'edit']);
                foreach ($variants['items'] as $variant) {
                    if (in_array((string) $variant['id'], $ids, true)) {
                        $remote[(string) $variant['id']] = [
                            'images' => isset($variant['image']['id']) ? [$variant['image']['id']] : [],
                            'stock' => $variant['stock_quantity'],
                            'status' => $variant['status'],
                        ];
                    }
                }
            }
        }
    }
    ksort($remote);
    if (count($remote) !== count($selected) || count($mappings) !== count($selected)) {
        throw new RuntimeException('Every selected product must have exactly one reachable mapped destination.');
    }

    return ['ids' => $ids, 'remote' => $remote];
};
$before = $snapshot();
$runId = null;
try {
    $api('PUT', 'configuration', $settings);
    for ($replay = 0; $replay < 2; ++$replay) {
        $start = microtime(true);
        $queued = $api('POST', 'sync', ['confirmed' => true]);
        $runId = $queued['runId'];
        echo json_encode(['queuedRun' => $runId, 'selected' => count($selected), 'replay' => $replay], JSON_THROW_ON_ERROR).PHP_EOL;
        do {
            sleep(3);
            $run = $database->fetchAssociative(
                'SELECT status, processed_items, failed_items, created_items FROM integration_import_runs WHERE tenant_id = :tenant AND connection_id = :connection AND id = :run',
                $parameters + ['run' => $runId],
            );
            if (microtime(true) - $start > 900) {
                throw new RuntimeException('Acceptance timed out; inspect the visible run before retrying.');
            }
        } while (in_array($run['status'], ['queued', 'running'], true));
        if ($run['status'] !== 'completed' || (int) $run['failed_items'] !== 0 || (int) $run['created_items'] !== 0) {
            throw new RuntimeException('Expected completed mapped updates without creates or failures: '.json_encode($run));
        }
        if ($snapshot() !== $before) {
            throw new RuntimeException('Destination identities, images, stock or status changed unexpectedly.');
        }
        echo json_encode([
            'replay' => $replay, 'seconds' => round(microtime(true) - $start, 2),
            'run' => $run, 'unchangedIdsImagesStockStatus' => true,
            'simpleRoots' => 90, 'variableRoots' => 10,
            'withImages' => count(array_filter($before['remote'], static fn (array $item): bool => $item['images'] !== [])),
        ], JSON_THROW_ON_ERROR).PHP_EOL;
    }
} finally {
    $api('PUT', 'configuration', $saved);
    echo "Original export settings restored.\n";
}
