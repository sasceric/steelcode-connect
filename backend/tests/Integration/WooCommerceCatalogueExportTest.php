<?php

namespace App\Tests\Integration;

use App\Entity\Currency;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSecret;
use App\Entity\Locale;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\Tenant;
use App\Entity\TenantCurrency;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSelection;
use App\Integration\CatalogueExportSettings;
use App\Integration\CatalogueExportWorkflow;
use App\Integration\CatalogueMediaDelivery;
use App\Integration\ImportCancellation;
use App\Integration\IntegrationImportLogger;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use App\Integration\WooCommerceCatalogueDependencies;
use App\Integration\WooCommerceCataloguePayload;
use App\Integration\WooCommerceCatalogueReferences;
use App\Integration\WooCommerceClient;
use App\Message\ExportWooCommerceCatalogue;
use App\MessageHandler\ExportWooCommerceCatalogueHandler;
use App\Service\InventorySyncOutboxService;
use App\Service\ShopwareCatalogueSyncService;
use App\Integration\ShopwareCataloguePayload;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;

final class WooCommerceCatalogueExportTest extends KernelTestCase
{
    public static function productTypes(): array
    {
        return [
            'simple' => [false],
            'parent and variation' => [true],
            'multiple variation parents' => [true, true],
        ];
    }

    #[DataProvider('productTypes')]
    public function testQueuedCreateUpdateAmbiguousCommitReplayAndTenantIsolation(bool $variant, bool $mixed = false): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Woo export rollback fixture');
            $connection = new IntegrationConnection($tenant, 'woocommerce', 'Woo publication', ['channel'], ['baseUrl' => 'https://woo.example.test']);
            $connection->activate('Test');
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']) ?? new Locale('en-GB', 'English', 'English');
            $currency = $manager->getRepository(Currency::class)->findOneBy(['code' => 'EUR']) ?? new Currency('EUR', '€', 2);
            $product = new Product($tenant);
            $product->updateIdentity('WOO-EXPORT-1', null);
            $product->updatePrices([(string) $currency->getId() => ['gross' => 12, 'net' => 10]], [], []);
            $translation = new ProductTranslation($product, $locale, 'Publication fixture', 'Description', null, null, null, []);
            $settings = CatalogueExportSettings::normalize(['productIds' => [(string) $product->getId()], 'fields' => ['content', 'prices'], 'publicationMode' => 'activate'], 'woocommerce');
            $extra = [];
            if ($variant) {
                $parent = new Product($tenant);
                $parent->updateIdentity('WOO-EXPORT-PARENT', null);
                $group = new PropertyGroup($tenant, 'Color', 'color', 'text', true, true, 'alphanumeric', 0);
                $property = new Property($tenant, $group, 'Blue', 'blue', null, 0);
                $product->makeChildOf($parent, 'WOO-EXPORT-1', null, [(string) $group->getId() => (string) $property->getId()]);
                $settings['mappings'] = ['propertyGroup' => [(string) $group->getId() => '3'], 'property' => [(string) $property->getId() => '3:term:7']];
                $extra = [$parent, $group, $property, new ProductTranslation($parent, $locale, 'Parent fixture')];
                if ($mixed) {
                    $secondParent = new Product($tenant);
                    $secondParent->updateIdentity('WOO-EXPORT-SECOND-PARENT', null);
                    $secondVariant = new Product($tenant);
                    $secondVariant->makeChildOf($secondParent, 'WOO-EXPORT-SECOND-VARIANT', null, [(string) $group->getId() => (string) $property->getId()]);
                    $secondVariant->updatePrices([(string) $currency->getId() => ['gross' => 12, 'net' => 10]], [], []);
                    $settings['productIds'][] = (string) $secondVariant->getId();
                    array_push($extra, $secondParent, $secondVariant, new ProductTranslation($secondParent, $locale, 'Second parent fixture'));
                }
            }
            $connection->updateConfiguration(['baseUrl' => 'https://woo.example.test', 'exportSettings' => $settings]);
            $cipher = new SecretCipher('test');
            foreach ([$tenant, $connection, $locale, $currency, new TenantCurrency($tenant, $currency), ...$extra, $product, $translation] as $entity) {
                $manager->persist($entity);
            }
            foreach (['consumerKey', 'consumerSecret'] as $key) {
                $encrypted = $cipher->encrypt('test-'.$key);
                $manager->persist(new IntegrationSecret($connection, $key, $encrypted['ciphertext'], $encrypted['nonce']));
            }
            $manager->flush();
            $tenantId = (string) $tenant->getId();
            $connectionId = (string) $connection->getId();
            $productId = (string) $product->getId();
            $world = [];
            $writes = 0;
            $uncertain = true;
            $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$world, &$writes, &$uncertain, $variant): MockResponse {
                $path = explode('/wp-json/wc/v3/', parse_url($url, PHP_URL_PATH))[1];
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
                if (str_starts_with($path, 'settings/')) {
                    return new MockResponse(json_encode([
                        ['id' => 'woocommerce_currency', 'value' => 'EUR'],
                        ['id' => 'woocommerce_prices_include_tax', 'value' => 'no'],
                    ]));
                }
                if ($path === 'taxes') {
                    return new MockResponse('[]');
                }
                if (str_ends_with($path, '/batch')) {
                    ++$writes;
                    $body = json_decode($options['body'], true);
                    $response = ['create' => [], 'update' => []];
                    foreach (['create', 'update'] as $action) {
                        foreach ($body[$action] as $payload) {
                            self::assertArrayNotHasKey('stock_quantity', $payload);
                            self::assertArrayNotHasKey('manage_stock', $payload);
                            if ($variant) {
                                if (str_contains($path, '/variations/')) {
                                    self::assertArrayNotHasKey('tax_status', $payload);
                                } else {
                                    self::assertSame('none', $payload['tax_status']);
                                }
                            }
                            $id = $payload['id'] ?? count($world) + 100;
                            $parentId = preg_match('#^products/(\\d+)/variations/#', $path, $match) ? (int) $match[1] : 0;
                            $world[$id] = array_replace($world[$id] ?? ['stock_quantity' => 87, 'manage_stock' => true, 'parent_id' => $parentId], $payload, ['id' => $id]);
                            $response[$action][] = $world[$id];
                        }
                    }
                    if ($uncertain) {
                        $uncertain = false;
                        return new MockResponse('{}', ['http_code' => 503]);
                    }

                    return new MockResponse(json_encode($response));
                }
                if ($path === 'products/attributes/3') {
                    return new MockResponse('{"id":3,"name":"Color"}');
                }
                if ($path === 'products/attributes/3/terms/7') {
                    return new MockResponse('{"id":7,"name":"Blue"}');
                }
                if ($path === 'products' || preg_match('#^products/\\d+/variations$#', $path)) {
                    self::assertSame('edit', $query['context'] ?? null, 'Conflict checks must not use storefront-filtered descriptions.');
                    $records = array_values(array_filter($world, static fn (array $item): bool => isset($query['include'])
                        ? in_array((string) $item['id'], explode(',', $query['include']), true)
                        : in_array($item['sku'], explode(',', $query['sku'] ?? ''), true)));

                    return new MockResponse(json_encode($records), ['response_headers' => ['x-wp-total: '.count($records)]]);
                }
                if (preg_match('#^products/(?:[0-9]+/variations/)?([0-9]+)$#', $path, $match)) {
                    self::assertSame('edit', $query['context'] ?? null);
                    return new MockResponse(json_encode($world[(int) $match[1]]));
                }

                throw new \LogicException('Unexpected request '.$method.' '.$path);
            });
            $client = new WooCommerceClient($http);
            $wooReferences = new WooCommerceCatalogueReferences($client, $cipher, new ArrayAdapter());
            $references = new CatalogueExportReferences(new ShopwareClient(new MockHttpClient()), $cipher, null, $wooReferences);
            $builder = new WooCommerceCataloguePayload($references);
            $pending = [];
            $bus = $this->createStub(MessageBusInterface::class);
            $bus->method('dispatch')->willReturnCallback(function ($message, $stamps = []) use (&$pending): Envelope {
                $pending[] = $message;
                return new Envelope($message, $stamps);
            });
            $cancellation = new ImportCancellation($database);
            $workflow = new CatalogueExportWorkflow($manager, $references, new CatalogueExportSelection(), new IntegrationImportLogger(sys_get_temp_dir().'/woo-export-tests'), $cancellation, $bus);
            $handler = new ExportWooCommerceCatalogueHandler($workflow, $manager, $client, $builder, new WooCommerceCatalogueDependencies($client, $builder, new CatalogueMediaDelivery('test', 'https://connect.example.test')), $cancellation, new InventorySyncOutboxService(), $wooReferences);
            $run = new IntegrationImportRun($tenant, $connection, 'export_sync');
            $manager->persist($run);
            $manager->flush();
            $runId = (string) $run->getId();
            $planId = (string) Uuid::v7();
            $database->insert('integration_export_plans', [
                'id' => $planId, 'tenant_id' => $tenantId, 'connection_id' => $connectionId,
                'run_id' => $runId, 'settings' => json_encode($settings),
                'settings_hash' => CatalogueExportSettings::hash($settings, 'woocommerce'), 'sync_mode' => 'manual',
            ]);
            $first = new ExportWooCommerceCatalogue($tenantId, $connectionId, $planId, $runId);
            $handler($first);
            self::assertSame(0, $writes, 'Preview must not write.');
            self::assertNotEmpty($pending);
            $iterations = 0;
            while ($pending !== []) {
                $message = array_shift($pending);
                $handler($message);
                self::assertLessThan(16, ++$iterations, 'Bounded phases must finish, not spin forever.');
            }
            self::assertSame('completed', $database->fetchOne('SELECT status FROM integration_import_runs WHERE id = :id', ['id' => $runId]));
            self::assertSame('completed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $planId]));
            $expectedWrites = $mixed ? 3 : ($variant ? 2 : 1);
            $expectedRecords = $mixed ? 4 : ($variant ? 2 : 1);
            $targetId = $mixed ? 102 : ($variant ? 101 : 100);
            self::assertSame($expectedWrites, $writes, 'A committed create followed by a 503 must be recovered, never recreated.');
            self::assertCount($expectedRecords, $world);
            self::assertSame(87, $world[$targetId]['stock_quantity']);
            self::assertSame((string) $targetId, $database->fetchOne(
                'SELECT external_product_id FROM product_channel_publications WHERE tenant_id = :tenant AND product_id = :product',
                ['tenant' => $tenantId, 'product' => $productId],
            ), 'Published destination identity must be available to stock sync.');
            self::assertSame(1, (int) $database->fetchOne("SELECT COUNT(*) FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = 'product' AND local_id = :product", ['tenant' => $tenantId, 'connection' => $connectionId, 'product' => $productId]));
            $handler($first);
            $handler(new ExportWooCommerceCatalogue((string) Uuid::v7(), $connectionId, $planId, $runId));
            self::assertSame($expectedWrites, $writes, 'Replay and cross-tenant messages must not write.');
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $product = $manager->find(Product::class, Uuid::fromString($productId));
            $translation = $manager->getRepository(ProductTranslation::class)->findOneBy(['product' => $product]);
            $translation->update('Updated publication fixture', null, 'Updated body', null, null, null);
            $manager->flush();
            $sync = new ShopwareCatalogueSyncService($manager, new CatalogueExportSelection(), $references, new ShopwareCataloguePayload(dirname(__DIR__, 2), $references), $bus, $builder);
            $world[$targetId]['sku'] = '';
            $queued = $sync->queue($connection);
            $iterations = 0;
            while ($pending !== []) {
                $handler(array_shift($pending));
                self::assertLessThan(16, ++$iterations);
            }
            $expectedWrites += $mixed ? 3 : ($variant ? 2 : 1);
            self::assertSame($expectedWrites, $writes);
            self::assertCount($expectedRecords, $world, 'Updates reuse the mapped Woo ID.');
            self::assertSame('Updated body', $world[$targetId]['description']);
            self::assertSame((string) $targetId, $database->fetchOne(
                'SELECT external_product_id FROM product_channel_publications WHERE tenant_id = :tenant AND product_id = :product',
                ['tenant' => $tenantId, 'product' => $productId],
            ), 'A publication update must not clear the stock-sync identity.');
            self::assertSame($product->getSku(), $world[$targetId]['sku'], 'Mapped records with blank destination SKUs remain updateable.');
            self::assertSame(87, $world[$targetId]['stock_quantity']);
            self::assertSame('completed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $queued['planId']]));
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $settings['automaticSync'] = true;
            $connection->updateConfiguration(['baseUrl' => 'https://woo.example.test', 'exportSettings' => $settings]);
            $manager->flush();
            $queued = $sync->queue($connection, [$productId]);
            while ($pending !== []) {
                $handler(array_shift($pending));
            }
            self::assertSame('completed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $queued['planId']]));
            $expectedWrites += $variant ? 2 : 1;
            self::assertCount($expectedRecords, $world, 'Automatic updates must reuse the same records.');
            $world[$targetId]['description'] = 'Destination edit';
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $queued = $sync->queue($connection, [$productId]);
            while ($pending !== []) {
                $handler(array_shift($pending));
            }
            self::assertSame('blocked', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $queued['planId']]));
            self::assertSame($expectedWrites, $writes, 'Automatic publication protects a destination-side edit.');
        } finally {
            if ($database->isTransactionActive()) {
                $database->rollBack();
            }
        }
    }
}
