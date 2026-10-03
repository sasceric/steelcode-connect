<?php

/**
 * Real exporter/ORM/database benchmark with an in-memory Shopware HTTP boundary.
 * Test database only; fixtures roll back unless --committed is passed against
 * a dedicated *_benchmark_test database. Drop that dedicated database afterwards.
 * Usage: APP_ENV=test APP_DEBUG=0 DATABASE_URL=... php tests/Benchmark/catalogue-scale.php 20000
 */

use App\Entity\Currency;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\Locale;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\Tax;
use App\Entity\Tenant;
use App\Entity\TenantCurrency;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSelection;
use App\Integration\CatalogueExportSettings;
use App\Integration\ImportCancellation;
use App\Integration\IntegrationImportLogger;
use App\Integration\SecretCipher;
use App\Integration\ShopwareCataloguePayload;
use App\Integration\ShopwareClient;
use App\Kernel;
use App\Message\ExportShopwareCatalogue;
use App\MessageHandler\ExportShopwareCatalogueHandler;
use App\Service\InventorySyncOutboxService;
use App\Service\ShopwareCatalogueSyncService;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__).'/bootstrap.php';

$count = filter_var($argv[1] ?? 100, FILTER_VALIDATE_INT);
if (($_SERVER['APP_ENV'] ?? '') !== 'test' || !$count || $count < 1 || $count > 20000) {
    throw new RuntimeException('Use APP_ENV=test and a product count between 1 and 20000.');
}
$kernel = new Kernel('test', false);
$kernel->boot();
$original = $kernel->getContainer()->get('test.service_container')->get('doctrine')->getManager();
$counter = new class extends AbstractLogger {
    public int $queries = 0;

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (str_starts_with((string) $message, 'Executing')) {
            ++$this->queries;
        }
    }
};
$dbConfig = new Configuration();
$dbConfig->setMiddlewares([new Middleware($counter)]);
$database = DriverManager::getConnection($original->getConnection()->getParams(), $dbConfig);
$databaseName = $database->fetchOne('SELECT current_database()');
if (!str_ends_with($databaseName, '_test')) {
    throw new RuntimeException('Refusing to seed a non-test database.');
}
$committed = in_array('--committed', $argv, true);
$concurrent = in_array('--concurrent', $argv, true);
$mixed = in_array('--mixed', $argv, true);
$faults = in_array('--faults', $argv, true);
if ($faults && (!$concurrent || !in_array('--doctrine-queue', $argv, true))) {
    throw new RuntimeException('Fault acceptance requires concurrent Doctrine queues so real delays are respected.');
}
if ($concurrent && (!$committed || !extension_loaded('pcntl'))) {
    throw new RuntimeException('Concurrent acceptance requires --committed, a dedicated benchmark database and pcntl.');
}
if ($committed && !str_ends_with($databaseName, '_benchmark_test')) {
    throw new RuntimeException('Committed fixtures require a dedicated *_benchmark_test database.');
}
$manager = new EntityManager($database, $original->getConfiguration(), $original->getEventManager());
$bus = new class implements MessageBusInterface {
    public array $lanes = [];
    public int $deliveries = 0;

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $lane = $envelope->last(TransportNamesStamp::class)?->getTransportNames()[0] ?? 'async';
        $this->lanes[$lane] ??= new SplQueue();
        $this->lanes[$lane]->enqueue($envelope->getMessage());

        return $envelope;
    }
};
$worlds = [];
$requests = 0;
$writes = 0;
$injectedFaults = [];
$http = new MockHttpClient(function (string $method, string $url, array $options) use (&$worlds, &$requests, &$writes, &$injectedFaults, $faults): MockResponse {
    ++$requests;
    $host = parse_url($url, PHP_URL_HOST);
    $path = parse_url($url, PHP_URL_PATH);
    if (!isset($worlds[$host])) {
        throw new RuntimeException('Unexpected destination: no external HTTP is permitted.');
    }
    if ($path === '/api/oauth/token') {
        return new MockResponse('{"access_token":"benchmark","expires_in":3600}');
    }
    if ($faults && $path === '/api/search/product') {
        if (!isset($injectedFaults[$host]['rateLimit'])) {
            $injectedFaults[$host]['rateLimit'] = true;

            return new MockResponse('{}', ['http_code' => 429, 'response_headers' => ['retry-after: 1']]);
        }
        if (!isset($injectedFaults[$host]['timeout'])) {
            $injectedFaults[$host]['timeout'] = true;
            throw new TransportException('Injected benchmark timeout; no real HTTP request.');
        }
    }
    $criteria = json_decode($options['body'] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
    if ($path === '/api/_action/sync') {
        foreach ($criteria as $operation) {
            foreach ($operation['payload'] as $payload) {
                $worlds[$host][$operation['entity']][$payload['id']] = array_replace(
                    $worlds[$host][$operation['entity']][$payload['id']] ?? [],
                    $payload,
                );
                ++$writes;
            }
        }
        if ($faults && !isset($injectedFaults[$host]['committed503'])) {
            $injectedFaults[$host]['committed503'] = true;

            return new MockResponse('{}', ['http_code' => 503, 'response_headers' => ['retry-after: 1']]);
        }

        return new MockResponse('{}');
    }
    $entity = substr($path, strlen('/api/search/'));
    $records = $worlds[$host][$entity] ?? [];
    if (isset($criteria['ids'])) {
        $records = array_intersect_key($records, array_flip($criteria['ids']));
    } elseif ($entity === 'product' && isset($criteria['filter'][0]['queries'])) {
        $queries = $criteria['filter'][0]['queries'];
        $ids = array_flip($queries[0]['value']);
        $skus = array_flip($queries[1]['value']);
        $records = array_filter($records, static fn (array $record, string $id): bool => isset($ids[$id])
            || isset($skus[$record['productNumber']]), ARRAY_FILTER_USE_BOTH);
    }
    $total = count($records);
    $limit = $criteria['limit'] ?? 25;
    $records = array_slice($records, (($criteria['page'] ?? 1) - 1) * $limit, $limit, true);
    $data = [];
    foreach ($records as $id => $attributes) {
        $data[] = ['id' => $id, 'attributes' => $attributes];
    }

    return new MockResponse(json_encode(['data' => $data, 'total' => $total], JSON_THROW_ON_ERROR));
});
$client = new ShopwareClient($http);
$references = new CatalogueExportReferences($client, new SecretCipher('benchmark'), new ArrayAdapter());
$builder = new ShopwareCataloguePayload(dirname(__DIR__, 2), $references);
$handler = new ExportShopwareCatalogueHandler(
    $manager,
    $client,
    $references,
    new CatalogueExportSelection(),
    $builder,
    new IntegrationImportLogger(sys_get_temp_dir().'/connect-scale-benchmark'),
    new ImportCancellation($database),
    new InventorySyncOutboxService(),
    $bus,
);

/** Seed a tenant from a real ORM product, cloning only fixtures through SQL. */
$seed = function (string $label, int $size) use ($manager, $database, &$worlds, $mixed): array {
    $tenant = new Tenant('Scale fixture '.$label);
    $connection = new IntegrationConnection($tenant, 'shopware', 'Benchmark '.$label, ['channel'], []);
    $connection->activate('Fixture');
    $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']);
    $currency = $manager->getRepository(Currency::class)->findOneBy(['code' => 'EUR']);
    if (!$locale instanceof Locale) {
        $locale = new Locale('en-GB', 'English', 'English');
        $manager->persist($locale);
    }
    if (!$currency instanceof Currency) {
        $currency = new Currency('EUR', '€', 2);
        $manager->persist($currency);
    }
    $tax = new Tax($tenant, 'Fixture VAT', '20.00');
    $group = new PropertyGroup($tenant, 'Colour', 'colour', 'text', true, true, 'position', 0);
    $option = new Property($tenant, $group, 'Blue', 'blue', null, 0);
    $product = new Product($tenant);
    $product->updateIdentity('SCALE-'.$label.'-0', null);
    $product->updateReferences($tax, null, null, null, null);
    $product->updatePrices([[
        'currencyId' => (string) $currency->getId(),
        'gross' => 12,
        'net' => 10,
        'linked' => true,
    ]], [], []);
    $translation = new ProductTranslation($product, $locale, 'Scale '.$label.' product');
    foreach ([$tenant, $connection, $tax, $group, $option, $product, $translation, new TenantCurrency($tenant, $currency)] as $entity) {
        $manager->persist($entity);
    }
    $channel = str_repeat('a', 32);
    $language = str_repeat('b', 32);
    $remoteCurrency = str_repeat('c', 32);
    $remoteTax = str_repeat('d', 32);
    $remoteLocale = str_repeat('e', 32);
    $settings = CatalogueExportSettings::normalize([
        'scope' => 'all',
        'automaticSync' => true,
        'salesChannelId' => $channel,
        'fields' => ['content'],
        'mappings' => [
            'locale' => [(string) $locale->getId() => $language],
            'currency' => [(string) $currency->getId() => $remoteCurrency],
            'tax' => [(string) $tax->getId() => $remoteTax],
            'property' => [(string) $option->getId() => str_repeat('f', 32)],
        ],
    ]);
    $host = 'scale-'.$label.'.invalid';
    $connection->updateConfiguration(['baseUrl' => 'https://'.$host, 'exportSettings' => $settings]);
    $manager->flush();
    $tenantId = (string) $tenant->getId();
    $connectionId = (string) $connection->getId();
    $productId = (string) $product->getId();
    $row = $database->fetchAssociative('SELECT * FROM products WHERE tenant_id = :tenant AND id = :id', [
        'tenant' => $tenantId,
        'id' => $productId,
    ]);
    $columns = array_keys($row);
    $select = array_map(static fn (string $column): string => match ($column) {
        'id' => 'gen_random_uuid()',
        'sku' => "p.sku || '-' || g.n",
        default => 'p.'.$database->quoteIdentifier($column),
    }, $columns);
    if ($size > 1) {
        $database->executeStatement(
            'INSERT INTO products ('.implode(', ', array_map($database->quoteIdentifier(...), $columns)).') SELECT '
                .implode(', ', $select).' FROM products p CROSS JOIN generate_series(1, :size) g(n) WHERE p.tenant_id = :tenant AND p.id = :id',
            ['tenant' => $tenantId, 'id' => $productId, 'size' => $size - 1],
        );
        $database->executeStatement(<<<'SQL'
INSERT INTO product_translations (id, product_id, locale_id, name, custom_fields)
SELECT gen_random_uuid(), p.id, :locale, p.sku, '[]'
FROM products p WHERE p.tenant_id = :tenant AND p.id <> :id
SQL, ['tenant' => $tenantId, 'id' => $productId, 'locale' => (string) $locale->getId()]);
    }
    if ($mixed && $size >= 4) {
        // One variant and its parent in every four records; the rest are simple.
        // Both ends are resolved inside the fixture tenant, never application data.
        $database->executeStatement(<<<'SQL'
WITH numbered AS (
    SELECT id, ROW_NUMBER() OVER (ORDER BY sku) AS n FROM products WHERE tenant_id = :tenant
)
UPDATE products child SET parent_id = parent.id
FROM numbered variant JOIN numbered parent ON parent.n = variant.n - 1
WHERE variant.n % 4 = 3 AND child.id = variant.id AND child.tenant_id = :tenant
SQL, ['tenant' => $tenantId]);
        $database->executeStatement(<<<'SQL'
INSERT INTO product_variant_option_values (id, tenant_id, product_id, property_id)
SELECT gen_random_uuid(), :tenant, id, :property FROM products WHERE tenant_id = :tenant AND parent_id IS NOT NULL
SQL, ['tenant' => $tenantId, 'property' => (string) $option->getId()]);
    }
    $worlds[$host] = [
        'sales-channel' => [$channel => ['active' => true, 'name' => 'Fixture']],
        'language' => [$language => ['localeId' => $remoteLocale]],
        'locale' => [$remoteLocale => ['code' => 'en-GB']],
        'currency' => [$remoteCurrency => ['isoCode' => 'EUR']],
        'tax' => [$remoteTax => ['taxRate' => 20]],
        'property-group-option' => [str_repeat('f', 32) => ['name' => 'Blue', 'groupId' => str_repeat('1', 32)]],
        'product' => [],
    ];
    $externalIds = [];
    $parents = [];
    foreach ($database->executeQuery('SELECT id, sku, parent_id FROM products WHERE tenant_id = :tenant', ['tenant' => $tenantId])->iterateAssociative() as $record) {
        $externalId = str_replace('-', '', Uuid::v5(
            Uuid::fromString(Uuid::NAMESPACE_URL),
            'connect:'.$tenantId.':'.$connectionId.':product:'.$record['id'],
        )->toRfc4122());
        $worlds[$host]['product'][$externalId] = [
            'id' => $externalId,
            'productNumber' => $record['sku'],
            'name' => 'Before update',
            'active' => false,
            'stock' => 47,
        ];
        $externalIds[$record['id']] = $externalId;
        if ($record['parent_id'] !== null) {
            $parents[$record['id']] = $record['parent_id'];
        }
    }
    foreach ($parents as $id => $parentId) {
        $worlds[$host]['product'][$externalIds[$id]]['parentId'] = $externalIds[$parentId];
    }
    $size = count($worlds[$host]['product']);
    $variants = count($parents);

    return compact('tenantId', 'connectionId', 'productId', 'settings', 'host', 'size', 'variants');
};

if (!$committed) {
    $database->beginTransaction();
}
try {
    $bulk = $seed('bulk', $count);
    $small = $seed('small', $concurrent ? 25 : 1);
    if ($concurrent) {
        $medium = $seed('medium', min($count, 1000));
        require __DIR__.'/concurrent-catalogue.php';
        $runConcurrent([$bulk, $medium, $small]);

        return;
    }
    $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($bulk['connectionId']));
    $run = new IntegrationImportRun($connection->getTenant(), $connection, 'export_sync');
    $manager->persist($run);
    $manager->flush();
    $planId = (string) Uuid::v7();
    $runId = (string) $run->getId();
    $database->insert('integration_export_plans', [
        'id' => $planId,
        'tenant_id' => $bulk['tenantId'],
        'connection_id' => $bulk['connectionId'],
        'run_id' => $runId,
        'settings' => json_encode($bulk['settings'], JSON_THROW_ON_ERROR),
        'settings_hash' => CatalogueExportSettings::hash($bulk['settings']),
        'sync_mode' => 'manual',
    ]);
    $manager->clear();
    $counter->queries = 0;
    $requests = 0;
    $started = microtime(true);
    $handler(new ExportShopwareCatalogue($bulk['tenantId'], $bulk['connectionId'], $planId, $runId, true));
    $smallStarted = microtime(true);
    $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($small['connectionId']));
    $smallPlan = (new ShopwareCatalogueSyncService(
        $manager,
        new CatalogueExportSelection(),
        $references,
        $builder,
        $bus,
    ))->queue($connection, [$small['productId']]);
    $smallSeconds = null;
    $maxChunk = 0.0;
    $deliveries = 1;
    while (true) {
        $worked = false;
        foreach (['catalogue', 'async'] as $lane) {
            if (!isset($bus->lanes[$lane]) || $bus->lanes[$lane]->isEmpty()) {
                continue;
            }
            $worked = true;
            $chunkStarted = microtime(true);
            $handler($bus->lanes[$lane]->dequeue());
            $maxChunk = max($maxChunk, microtime(true) - $chunkStarted);
            ++$deliveries;
            if ($smallSeconds === null && $database->fetchOne(
                'SELECT status FROM integration_export_plans WHERE id = :id AND tenant_id = :tenant',
                ['id' => $smallPlan['planId'], 'tenant' => $small['tenantId']],
            ) === 'completed') {
                $smallSeconds = microtime(true) - $smallStarted;
            }
            if ($deliveries % 100 === 0) {
                fwrite(STDERR, sprintf("%d deliveries, %.1fs elapsed, %.1fMB memory\n", $deliveries, microtime(true) - $started, memory_get_usage(true) / 1048576));
            }
        }
        if (!$worked) {
            break;
        }
    }
    $seconds = microtime(true) - $started;
    $outcome = $database->fetchAssociative('SELECT status, processed_items, failed_items FROM integration_import_runs WHERE tenant_id = :tenant AND id = :run', [
        'tenant' => $bulk['tenantId'],
        'run' => $runId,
    ]);
    if ($outcome['status'] !== 'completed' || (int) $outcome['processed_items'] !== $count || (int) $outcome['failed_items'] !== 0 || $smallSeconds === null) {
        throw new RuntimeException('Benchmark correctness failed: '.json_encode($outcome));
    }
    foreach ($worlds as $world) {
        foreach ($world['product'] as $product) {
            if ($product['stock'] !== 47 || $product['active'] !== false) {
                throw new RuntimeException('Catalogue publication changed stock or active status.');
            }
        }
    }
    $movementCount = $database->fetchOne('SELECT COUNT(*) FROM inventory_movements WHERE tenant_id IN (:bulk, :small)', [
        'bulk' => $bulk['tenantId'],
        'small' => $small['tenantId'],
    ]);
    echo json_encode([
        'products' => $count,
        'seconds' => round($seconds, 3),
        'productsPerSecond' => round($count / $seconds, 2),
        'databaseQueries' => $counter->queries,
        'httpRequestsSimulated' => $requests,
        'writesSimulated' => $writes,
        'deliveries' => $deliveries,
        'maxChunkSeconds' => round($maxChunk, 3),
        'smallTenantCompletionSeconds' => round($smallSeconds, 3),
        'peakMemoryMB' => round(memory_get_peak_usage(true) / 1048576, 1),
        'inventoryMovements' => (int) $movementCount,
        'committedChunks' => $committed,
        'boundary' => 'Real PostgreSQL/ORM/exporter; simulated zero-latency HTTP; cooperatively interleaved queue lanes, not a production load test.',
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    if (!$committed) {
        $database->rollBack();
    }
    $manager->clear();
    $kernel->shutdown();
}
