<?php

namespace App\Tests\Integration;

use App\Controller\Api\IntegrationExportController;
use App\Controller\Api\IntegrationImportController;
use App\Entity\Currency;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\CategoryProduct;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\Locale;
use App\Entity\Product;
use App\Entity\ProductBrand;
use App\Entity\ProductTranslation;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\TenantCurrency;
use App\Entity\Tax;
use App\Entity\Manufacturer;
use App\Entity\ManufacturerTranslation;
use App\Entity\CategoryTranslation;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\ProductPropertyAssignment;
use App\Entity\Unit;
use App\Entity\DeliveryTime;
use App\Entity\CustomField;
use App\Entity\CustomFieldSet;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSelection;
use App\Integration\CatalogueExportSettings;
use App\Integration\ImportCancellation;
use App\Integration\IntegrationImportLogger;
use App\Integration\SecretCipher;
use App\Integration\ShopwareCataloguePayload;
use App\Integration\ShopwareClient;
use App\Message\ExportShopwareCatalogue;
use App\MessageHandler\ExportShopwareCatalogueHandler;
use App\Service\InventorySyncOutboxService;
use App\Service\ShopwareCatalogueSyncService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class ShopwareCatalogueExportTest extends KernelTestCase
{
    public function testProgressEndpointReportsQueuedAndCancelledRunsWithoutAWorker(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Export progress regression');
            $user = new User('export-progress-'.bin2hex(random_bytes(6)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $connection = new IntegrationConnection($tenant, 'shopware', 'Progress', ['channel'], []);
            $connection->activate('Test');
            foreach ([$tenant, $user, $membership, $connection] as $entity) {
                $manager->persist($entity);
            }
            $manager->flush();
            $settings = CatalogueExportSettings::defaults();
            $message = $this->plan($manager, $connection, $settings, true);
            $tokenStorage = new TokenStorage();
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $services = new Container();
            $services->set('security.token_storage', $tokenStorage);
            $references = new CatalogueExportReferences(new ShopwareClient(new MockHttpClient()), new SecretCipher('test'));
            $controller = new IntegrationExportController($manager, $references, $this->createStub(MessageBusInterface::class));
            $controller->setContainer($services);
            $payload = json_decode($controller->latest((string) $connection->getId(), new Request())->getContent(), true);
            self::assertSame('queued', $payload['run']['status']);
            self::assertSame(0, $payload['run']['processedItems']);
            self::assertNotEmpty($payload['run']['updatedAt']);
            $importController = new IntegrationImportController();
            $importController->setContainer($services);
            $response = $importController->cancel(
                (string) $connection->getId(),
                $message->runId,
                $manager,
                self::getContainer()->get('translator'),
                new IntegrationImportLogger(sys_get_temp_dir().'/connect-export-tests'),
            );
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('cancelled', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $message->planId]));
            // Also cover the older inconsistent rows created before this fix.
            $database->update('integration_export_plans', ['status' => 'preparing'], ['id' => $message->planId]);
            $payload = json_decode($controller->latest((string) $connection->getId(), new Request())->getContent(), true);
            self::assertSame('cancelled', $payload['plan']['status']);
            self::assertSame('cancelled', $payload['run']['status']);
            self::assertNotEmpty($payload['run']['completedAt']);
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    public function testCategoryBrandIntersectionVariantExpansionAndParentNames(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Export selection regression');
            $connection = new IntegrationConnection($tenant, 'shopware', 'Selection', ['channel'], []);
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']);
            $category = new Category($tenant, 0);
            $childCategory = new Category($tenant, 0);
            $childCategory->move($category, 0);
            $brand = new Brand($tenant);
            $parent = new Product($tenant);
            $parent->updateIdentity('SELECTION-PARENT', null);
            $variant = new Product($tenant);
            $variant->makeChildOf($parent, 'SELECTION-VARIANT', null, []);
            $manual = new Product($tenant);
            $manual->updateIdentity('MANUAL', null);
            foreach ([
                $tenant, $connection, $category, $childCategory, $brand,
                $parent, $variant, $manual,
                new CategoryProduct($childCategory, $parent, 0),
                new ProductBrand($parent, $brand),
                new ProductTranslation($parent, $locale, 'Parent display name'),
                new ProductTranslation($variant, $locale, 'SELECTION-VARIANT'),
            ] as $entity) {
                $manager->persist($entity);
            }
            $manager->flush();
            $settings = CatalogueExportSettings::normalize([
                'categoryIds' => [(string) $category->getId()],
                'brandIds' => [(string) $brand->getId()],
                'productIds' => [(string) $manual->getId()],
                'includeVariants' => false,
                'fields' => ['content'],
                'mappings' => ['locale' => [(string) $locale->getId() => str_repeat('a', 32)]],
            ]);
            $selection = new CatalogueExportSelection();
            $ids = iterator_to_array($selection->selectedIds($database, $connection, $settings), false);
            self::assertSame(count($ids), $selection->count($database, $connection, $settings));
            self::assertEqualsCanonicalizing([(string) $parent->getId(), (string) $manual->getId()], $ids);
            $settings['includeDescendants'] = false;
            self::assertSame([(string) $manual->getId()], iterator_to_array($selection->selectedIds($database, $connection, $settings), false));
            $settings['includeDescendants'] = true;
            $settings['includeVariants'] = true;
            self::assertCount(3, iterator_to_array($selection->selectedIds($database, $connection, $settings), false));
            $all = CatalogueExportSettings::normalize(['scope' => 'all']);
            self::assertCount(3, $selection->page($database, $connection, $all, null));
            $first = $selection->page($database, $connection, $all, null, 1);
            self::assertCount(2, $selection->page($database, $connection, $all, $first[0]));
            self::assertSame(3, $selection->count($database, $connection, $all));
            $settings['categoryIds'] = [];
            $settings['brandIds'] = [];
            $settings['productIds'] = [(string) $variant->getId()];
            $settings['excludeIds'] = [(string) $parent->getId()];
            self::assertSame(
                [(string) $parent->getId(), (string) $variant->getId()],
                iterator_to_array($selection->selectedIds($database, $connection, $settings), false),
                'Required parents stay included even when explicitly excluded.',
            );
            $client = new ShopwareClient(new MockHttpClient());
            $references = new CatalogueExportReferences($client, new SecretCipher('test'));
            $builder = new ShopwareCataloguePayload(dirname(__DIR__, 2), $references);
            $built = $builder->build($variant, $connection, $manager, $settings, false);
            self::assertSame('Parent display name', $built['name']);
            self::assertSame('Parent display name', $built['payload']['name']);
            self::assertSame('Parent display name', $built['payload']['translations'][str_repeat('a', 32)]['name']);
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    public function testPreviewPublicationReplayConflictAndTenantIsolation(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Catalogue export regression');
            $otherTenant = new Tenant('Foreign export regression');
            $connection = new IntegrationConnection($tenant, 'shopware', 'Export test', ['source', 'channel'], ['baseUrl' => 'http://export.test']);
            $connection->activate('Test');
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']);
            $currency = $manager->getRepository(Currency::class)->findOneBy(['code' => 'EUR']);
            if (!$currency instanceof Currency) {
                $currency = new Currency('EUR', '€', 2);
                $manager->persist($currency);
            }
            $tax = new Tax($tenant, 'Export VAT', '20.00');
            $product = new Product($tenant);
            $product->updateIdentity('CONNECT-EXPORT-1', '1234567890123');
            $product->updateReferences($tax, null, null, null, null);
            $product->updatePrices([['currencyId' => (string) $currency->getId(), 'gross' => 12.0, 'net' => 10.0, 'linked' => true]], [], []);
            $foreignProduct = new Product($otherTenant);
            $foreignProduct->updateIdentity('FOREIGN', null);
            foreach ([$tenant, $otherTenant, $connection, $tax, $product, $foreignProduct, new TenantCurrency($tenant, $currency), new ProductTranslation($product, $locale, 'Export product')] as $entity) {
                $manager->persist($entity);
            }
            $channelId = str_repeat('a', 32);
            $taxId = str_repeat('b', 32);
            $currencyId = str_repeat('c', 32);
            $languageId = str_repeat('d', 32);
            $settings = CatalogueExportSettings::normalize([
                'productIds' => [(string) $product->getId()],
                'salesChannelId' => $channelId,
                'fields' => ['content', 'prices'],
                'mappings' => [
                    'locale' => [(string) $locale->getId() => $languageId],
                    'currency' => [(string) $currency->getId() => $currencyId],
                    'tax' => [(string) $tax->getId() => $taxId],
                ],
            ]);
            $connection->updateConfiguration(['baseUrl' => 'http://export.test', 'exportSettings' => $settings]);
            $manager->flush();
            $tenantId = (string) $tenant->getId();
            $connectionId = (string) $connection->getId();
            $productId = (string) $product->getId();
            $foreignProductId = (string) $foreignProduct->getId();
            $world = [
                'sales-channel' => [$channelId => ['active' => true, 'name' => 'Test shop', 'languageId' => $languageId, 'currencyId' => $currencyId]],
                'tax' => [$taxId => ['taxRate' => 20]],
                'currency' => [$currencyId => ['isoCode' => 'EUR']],
                'language' => [$languageId => ['name' => 'English']],
                'product' => [],
            ];
            $writes = [];
            $throttle = false;
            $syncRequestSizes = [];
            $rejectSku = null;
            $unavailableAfterWrite = false;
            $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$world, &$writes, &$throttle, &$syncRequestSizes, &$rejectSku, &$unavailableAfterWrite): MockResponse {
                $path = parse_url($url, PHP_URL_PATH);
                if ($throttle && $path === '/api/search/sales-channel') {
                    $throttle = false;

                    return new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After: 120']]);
                }
                if ($path === '/api/oauth/token') {
                    return new MockResponse(json_encode(['access_token' => 'test', 'expires_in' => 3600]));
                }
                $body = json_decode($options['body'] ?? '{}', true);
                if ($path === '/api/_action/sync') {
                    $write = $body['connect-catalogue'];
                    if ($write['entity'] === 'product') {
                        $syncRequestSizes[] = count($write['payload']);
                        if ($rejectSku !== null && in_array($rejectSku, array_column($write['payload'], 'productNumber'), true)) {
                            return new MockResponse('{"errors":[{"code":"VIOLATION"}]}', ['http_code' => 400]);
                        }
                    }
                    foreach ($write['payload'] as $payload) {
                        $writes[] = $payload;
                        $world[$write['entity']][$payload['id']] = array_replace($world[$write['entity']][$payload['id']] ?? [], $payload);
                    }
                    if ($unavailableAfterWrite && $write['entity'] === 'product') {
                        $unavailableAfterWrite = false;

                        return new MockResponse('', ['http_code' => 503]);
                    }

                    return new MockResponse('{}');
                }
                $entity = substr($path, strlen('/api/search/'));
                $records = $world[$entity] ?? [];
                if (isset($body['ids'])) {
                    $records = array_intersect_key($records, array_flip($body['ids']));
                } elseif ($entity === 'product' && isset($body['filter'][0]['queries'])) {
                    $queries = $body['filter'][0]['queries'];
                    $records = array_filter($records, static fn (array $record, string $id): bool => in_array($id, $queries[0]['value'], true) || in_array($record['productNumber'] ?? '', $queries[1]['value'], true), ARRAY_FILTER_USE_BOTH);
                }
                $total = count($records);
                $records = array_slice($records, (($body['page'] ?? 1) - 1) * ($body['limit'] ?? 25), $body['limit'] ?? 25, true);
                $data = [];
                foreach ($records as $id => $attributes) {
                    $data[] = ['id' => $id, 'attributes' => $attributes];
                }

                return new MockResponse(json_encode(['data' => $data, 'total' => $total]));
            });
            $client = new ShopwareClient($http);
            $references = new CatalogueExportReferences($client, new SecretCipher('test'));
            $builder = new ShopwareCataloguePayload(dirname(__DIR__, 2), $references);
            $selection = new CatalogueExportSelection();
            $handler = new ExportShopwareCatalogueHandler($manager, $client, $references, $selection, $builder, new IntegrationImportLogger(sys_get_temp_dir().'/connect-export-tests'), new ImportCancellation($database), new InventorySyncOutboxService());
            self::assertSame([$productId], iterator_to_array($selection->selectedIds($database, $connection, $settings), false));
            self::assertSame([], iterator_to_array($selection->selectedIds($database, $connection, CatalogueExportSettings::defaults()), false));
            self::assertFalse($references->owns($connection, $manager, 'product', $foreignProductId));
            self::assertCount(1, $references->localPage($connection, $manager, 'product', 1, 'CONNECT-EXPORT')['items']);
            $preview = $this->plan($manager, $connection, $settings, true);
            $handler($preview);
            self::assertSame('ready', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $preview->planId]));
            self::assertSame([], $writes, 'Preview must be read-only in Shopware.');
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $publish = $this->publication($manager, $connection, $preview->planId);
            $handler($publish);
            self::assertCount(1, $writes);
            self::assertSame(false, $writes[0]['active']);
            self::assertSame(0, $writes[0]['stock']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $writes[0]['id']);
            self::assertSame('completed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $preview->planId]));
            $externalId = $writes[0]['id'];
            $world['product'][$externalId]['stock'] = 47;
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $replayPreview = $this->plan($manager, $connection, $settings, true);
            $handler($replayPreview);
            self::assertSame('update', $database->fetchOne('SELECT action FROM integration_export_items WHERE plan_id = :id', ['id' => $replayPreview->planId]));
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $handler($this->publication($manager, $connection, $replayPreview->planId));
            self::assertCount(2, $writes);
            self::assertArrayNotHasKey('stock', $writes[1]);
            self::assertSame(47, $world['product'][$externalId]['stock']);
            self::assertCount(1, $world['product']);
            // A local edit invalidates the frozen preview before any write.
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $changedPreview = $this->plan($manager, $connection, $settings, true);
            $handler($changedPreview);
            $product = $manager->find(Product::class, Uuid::fromString($productId));
            $product->updateIdentity('CONNECT-EXPORT-EDITED', null);
            $manager->flush();
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $handler($this->publication($manager, $connection, $changedPreview->planId));
            self::assertCount(2, $writes);
            self::assertSame('failed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $changedPreview->planId]));
            $handler(new ExportShopwareCatalogue((string) $otherTenant->getId(), $connectionId, $changedPreview->planId, $changedPreview->runId, true));
            self::assertCount(2, $writes);
            // Frozen previews must never follow a subsequently changed destination.
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $endpointPreview = $this->plan($manager, $connection, $settings, true);
            $handler($endpointPreview);
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $connection->updateConfiguration(['baseUrl' => 'http://different-shop.test', 'exportSettings' => $settings]);
            $manager->flush();
            $handler($this->publication($manager, $connection, $endpointPreview->planId));
            self::assertCount(2, $writes, 'Changing the endpoint invalidates its frozen preview.');
            self::assertSame('failed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $endpointPreview->planId]));
            // Production deliveries yield durable chunks and retain one run/history record.
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $settings['scope'] = 'all';
            $settings['productIds'] = [];
            // Standard currency mappings resolve into the plan, not the saved configuration.
            unset($settings['mappings']['currency']);
            $connection->updateConfiguration(['baseUrl' => 'http://export.test', 'exportSettings' => $settings]);
            $tenant = $manager->find(Tenant::class, Uuid::fromString($tenantId));
            $tax = $manager->find(Tax::class, $tax->getId());
            $locale = $manager->find(Locale::class, $locale->getId());
            for ($index = 0; $index < 54; ++$index) {
                $next = new Product($tenant);
                $next->updateIdentity('CHUNKED-'.$index, null);
                $next->updateReferences($tax, null, null, null, null);
                $next->updatePrices([['currencyId' => (string) $currency->getId(), 'gross' => 12, 'net' => 10, 'linked' => true]], [], []);
                $manager->persist($next);
                $manager->persist(new ProductTranslation($next, $locale, 'Chunk '.$index));
            }
            $manager->flush();
            $continuations = [];
            $deliveryStamps = [];
            $bus = $this->createStub(MessageBusInterface::class);
            $bus->method('dispatch')->willReturnCallback(static function (object $message, array $stamps = []) use (&$continuations, &$deliveryStamps): Envelope {
                $continuations[] = $message;
                $deliveryStamps[] = $stamps;

                return new Envelope($message, $stamps);
            });
            $chunked = new ExportShopwareCatalogueHandler($manager, $client, $references, $selection, $builder, new IntegrationImportLogger(sys_get_temp_dir().'/connect-export-tests'), new ImportCancellation($database), new InventorySyncOutboxService(), $bus);
            $chunkPreview = $this->plan($manager, $connection, $settings, true);
            $chunked($chunkPreview);
            self::assertSame(25, (int) $database->fetchOne('SELECT COUNT(*) FROM integration_export_items WHERE plan_id = :plan', ['plan' => $chunkPreview->planId]));
            self::assertCount(1, $continuations);
            $chunked($chunkPreview);
            self::assertCount(1, $continuations, 'Duplicate delivery cannot advance a checkpoint twice.');
            self::assertSame(25, (int) $database->fetchOne('SELECT processed_items FROM integration_import_runs WHERE id = :id', ['id' => $chunkPreview->runId]));
            // A run created by the older non-atomic worker repairs its counters once.
            $olderSettings = json_decode($database->fetchOne('SELECT settings FROM integration_export_plans WHERE id = :id', ['id' => $chunkPreview->planId]), true);
            unset($olderSettings['_atomicCountsPhase']);
            $database->update('integration_export_plans', ['settings' => json_encode($olderSettings, JSON_THROW_ON_ERROR)], ['id' => $chunkPreview->planId]);
            $database->update('integration_import_runs', ['processed_items' => 0, 'created_items' => 0, 'updated_items' => 0], ['id' => $chunkPreview->runId]);
            while ($continuations !== []) {
                $chunked(array_shift($continuations));
            }
            self::assertSame('ready', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $chunkPreview->planId]));
            self::assertSame(55, (int) $database->fetchOne('SELECT processed_items FROM integration_import_runs WHERE id = :id', ['id' => $chunkPreview->runId]));
            self::assertCount(2, $writes, 'Chunked preview is still entirely read-only.');
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $chunkPublication = $this->publication($manager, $connection, $chunkPreview->planId);
            $chunked($chunkPublication);
            self::assertCount(2, $writes, 'Every source is preflighted before any publication chunk.');
            $deliveries = 0;
            while ($continuations !== []) {
                self::assertLessThan(20, ++$deliveries);
                $chunked(array_shift($continuations));
            }
            self::assertSame('completed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $chunkPreview->planId]));
            self::assertSame(55, (int) $database->fetchOne('SELECT processed_items FROM integration_import_runs WHERE id = :id', ['id' => $chunkPublication->runId]));
            self::assertCount(55, $world['product']);
            self::assertCount(57, $writes);
            // A throttled first chunk releases the worker, including before run.start().
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $retryPreview = $this->plan($manager, $connection, $settings, true);
            $throttle = true;
            $chunked($retryPreview);
            self::assertCount(1, $continuations);
            $lastStamps = end($deliveryStamps);
            $delays = array_values(array_filter($lastStamps, static fn (object $stamp): bool => $stamp instanceof DelayStamp));
            self::assertSame(120000, $delays[0]->getDelay());
            self::assertSame(1, (int) $database->fetchOne('SELECT retry_count FROM integration_export_plans WHERE id = :id', ['id' => $retryPreview->planId]));
            $chunked($retryPreview);
            self::assertCount(1, $continuations, 'The original delivery cannot bypass its delayed retry checkpoint.');
            while ($continuations !== []) {
                $chunked(array_shift($continuations));
            }
            self::assertSame('ready', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $retryPreview->planId]));
            self::assertCount(57, $writes, 'Retrying a preview must not write to Shopware.');
            // A change in a later preflight page blocks the first remote write.
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $chunked($this->publication($manager, $connection, $retryPreview->planId));
            $uncheckedId = $database->fetchOne(
                'SELECT product_id FROM integration_export_items WHERE plan_id = :plan AND preflight_checked = FALSE ORDER BY product_id LIMIT 1',
                ['plan' => $retryPreview->planId],
            );
            self::assertNotFalse($uncheckedId);
            $changed = $manager->find(Product::class, Uuid::fromString($uncheckedId));
            $changed->updateIdentity('CHANGED-AFTER-FIRST-PREFLIGHT', null);
            $manager->flush();
            while ($continuations !== []) {
                $chunked(array_shift($continuations));
            }
            self::assertSame('failed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $retryPreview->planId]));
            self::assertCount(57, $writes, 'Every preflight page must pass before any writes.');
            self::assertContains(25, $syncRequestSizes, 'Publication must use bounded API batches.');
            self::assertLessThanOrEqual(25, max($syncRequestSizes));

            // One invalid record in a rejected batch does not discard its valid neighbours.
            $database->executeStatement(
                "UPDATE product_translations SET name = name || ' batch QA' WHERE product_id IN (SELECT id FROM products WHERE tenant_id = :tenant)",
                ['tenant' => $tenantId],
            );
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $validationPreview = $this->plan($manager, $connection, $settings, true);
            $chunked($validationPreview);
            while ($continuations !== []) {
                $chunked(array_shift($continuations));
            }
            $rejectSku = $database->fetchOne(
                "SELECT sku FROM products WHERE tenant_id = :tenant AND sku LIKE 'CHUNKED-%' ORDER BY sku LIMIT 1",
                ['tenant' => $tenantId],
            );
            self::assertNotFalse($rejectSku);
            $beforeValidation = count($writes);
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $validationPublication = $this->publication($manager, $connection, $validationPreview->planId);
            $chunked($validationPublication);
            while ($continuations !== []) {
                $chunked(array_shift($continuations));
            }
            $validationCounts = $database->fetchAssociative('SELECT processed_items, failed_items FROM integration_import_runs WHERE id = :id', ['id' => $validationPublication->runId]);
            self::assertSame(55, (int) $validationCounts['processed_items']);
            self::assertSame(1, (int) $validationCounts['failed_items']);
            self::assertSame(54, count($writes) - $beforeValidation);

            // A 503 after the remote commit reconciles the started write instead of overwriting.
            $rejectSku = null;
            $database->executeStatement(
                "UPDATE product_translations SET name = name || ' recovery QA' WHERE product_id IN (SELECT id FROM products WHERE tenant_id = :tenant)",
                ['tenant' => $tenantId],
            );
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $uncertainPreview = $this->plan($manager, $connection, $settings, true);
            $chunked($uncertainPreview);
            while ($continuations !== []) {
                $chunked(array_shift($continuations));
            }
            $unavailableAfterWrite = true;
            $beforeRecovery = count($writes);
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $uncertainPublication = $this->publication($manager, $connection, $uncertainPreview->planId);
            $chunked($uncertainPublication);
            while ($continuations !== []) {
                $chunked(array_shift($continuations));
                if (!$unavailableAfterWrite && count($writes) > $beforeRecovery) {
                    break;
                }
            }
            self::assertSame(25, count($writes) - $beforeRecovery);
            self::assertSame(25, (int) $database->fetchOne('SELECT COUNT(*) FROM integration_export_items WHERE plan_id = :plan AND write_started = TRUE', ['plan' => $uncertainPreview->planId]));
            // Editing one acknowledged-looking record during the outage must still block it.
            $foreignId = $writes[$beforeRecovery]['id'];
            $world['product'][$foreignId]['name'] = 'External edit during outage';
            while ($continuations !== []) {
                $chunked(array_shift($continuations));
            }
            $recoveryCounts = $database->fetchAssociative('SELECT processed_items, failed_items FROM integration_import_runs WHERE id = :id', ['id' => $uncertainPublication->runId]);
            self::assertSame(55, (int) $recoveryCounts['processed_items']);
            self::assertSame(1, (int) $recoveryCounts['failed_items']);
            self::assertSame(55, count($writes) - $beforeRecovery, 'Committed records must not be written twice on redelivery.');
            self::assertSame('External edit during outage', $world['product'][$foreignId]['name']);
            self::assertSame(0, (int) $database->fetchOne(
                'SELECT COUNT(*) FROM product_channel_publications WHERE tenant_id = :tenant AND external_product_id IS NULL',
                ['tenant' => $tenantId],
            ), 'Creating or updating catalogue publication must preserve its stock-sync destination ID.');
            self::assertSame(0, (int) $database->fetchOne('SELECT COUNT(*) FROM inventory_movements WHERE tenant_id = :id', ['id' => $tenantId]));
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    public function testReferenceCreationAndAutomaticSyncUseTheSameReadOnlyPreviewAndGuardedPublisher(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Automatic catalogue regression');
            $connection = new IntegrationConnection($tenant, 'shopware', 'Auto test', ['channel'], ['baseUrl' => 'http://export.test']);
            $connection->activate('Test');
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']);
            $currency = $manager->getRepository(Currency::class)->findOneBy(['code' => 'EUR']);
            if (!$currency instanceof Currency) {
                $currency = new Currency('EUR', '€', 2);
                $manager->persist($currency);
            }
            $manufacturer = new Manufacturer($tenant);
            $category = new Category($tenant, 0);
            $child = new Category($tenant, 0);
            $child->move($category, 0);
            $group = new PropertyGroup($tenant, 'Material', 'material', 'text', true, true, 'position', 0);
            $property = new Property($tenant, $group, 'Steel', 'steel', null, 0);
            $unit = new Unit($tenant, 'piece', 'pc', ['en-GB' => 'Piece']);
            $deliveryTime = new DeliveryTime($tenant, ['en-GB' => 'Two days'], 1, 2, 'day');
            $set = new CustomFieldSet($tenant, 'woo_fields', ['en-GB' => 'Woo fields'], ['product'], 0);
            $field = new CustomField($tenant, $set, 'connect_test_material', 'text', ['en-GB' => 'Material'], [], 0);
            $tax = new Tax($tenant, 'VAT', '20.00');
            $product = new Product($tenant);
            $product->updateIdentity('AUTO-NEW-1', null);
            $product->updateReferences($tax, $unit, null, null, $deliveryTime);
            $product->updatePrices([['currencyId' => (string) $currency->getId(), 'gross' => 12, 'net' => 10, 'linked' => true]], [], []);
            $translation = new ProductTranslation($product, $locale, 'Auto product');
            $translation->update('Auto product', null, null, null, null, null, ['connect_test_material' => 'Steel']);
            foreach ([
                $tenant, $connection, $manufacturer, $category, $child, $group, $property, $unit, $deliveryTime,
                $set, $field, $tax, $product, $translation, new TenantCurrency($tenant, $currency),
                new ManufacturerTranslation($manufacturer, $locale, 'Connect test manufacturer'),
                new CategoryTranslation($category, $locale, 'Connect root'),
                new CategoryTranslation($child, $locale, 'Connect child'),
                new CategoryProduct($child, $product, 0), new ProductPropertyAssignment($tenant, $product, $property),
            ] as $entity) {
                $manager->persist($entity);
            }
            $manager->flush();
            $database->update('products', ['manufacturer_id' => (string) $manufacturer->getId()], ['id' => (string) $product->getId()]);
            $manager->refresh($product);
            $settings = CatalogueExportSettings::normalize([
                'scope' => 'all', 'automaticSync' => true, 'createMissingReferences' => true,
                'categoryRootId' => str_repeat('e', 32), 'salesChannelId' => str_repeat('a', 32),
                'fields' => ['content', 'prices', 'classification', 'fulfilment', 'customFields'],
                'mappings' => [
                    'locale' => [(string) $locale->getId() => str_repeat('d', 32)],
                    'currency' => [(string) $currency->getId() => str_repeat('c', 32)],
                ],
            ]);
            $connection->updateConfiguration(['baseUrl' => 'http://export.test', 'exportSettings' => $settings]);
            $manager->flush();
            $tenantId = (string) $tenant->getId();
            $connectionId = (string) $connection->getId();
            $productId = (string) $product->getId();
            $world = [
                'sales-channel' => [str_repeat('a', 32) => ['active' => true]],
                'language' => [str_repeat('d', 32) => ['name' => 'English']],
                'currency' => [str_repeat('c', 32) => ['isoCode' => 'EUR']],
                'category' => [str_repeat('e', 32) => ['name' => 'Navigation root']],
            ];
            $writes = [];
            $client = new ShopwareClient(new MockHttpClient(function (string $method, string $url, array $options) use (&$world, &$writes): MockResponse {
                $path = parse_url($url, PHP_URL_PATH);
                $body = json_decode($options['body'] ?? '{}', true);
                if ($path === '/api/oauth/token') {
                    return new MockResponse('{"access_token":"test","expires_in":3600}');
                }
                if ($path === '/api/_action/sync') {
                    $write = $body['connect-catalogue'];
                    $entity = str_replace('_', '-', $write['entity']);
                    foreach ($write['payload'] as $payload) {
                        $writes[] = $entity;
                        $world[$entity][$payload['id']] = array_replace_recursive($world[$entity][$payload['id']] ?? [], $payload);
                        if ($entity === 'tax') {
                            $world[$entity][$payload['id']]['taxRate'] = (float) $payload['taxRate'];
                        }
                    }

                    return new MockResponse('{}');
                }
                $entity = substr($path, strlen('/api/search/'));
                $records = $world[$entity] ?? [];
                if (isset($body['ids'])) {
                    $records = array_intersect_key($records, array_flip($body['ids']));
                } elseif (isset($body['filter'][0]['field'])) {
                    $filter = $body['filter'][0];
                    $records = array_filter($records, static fn (array $row): bool => ($row[$filter['field']] ?? null) === $filter['value']);
                }
                $data = [];
                foreach ($records as $id => $attributes) {
                    $data[] = ['id' => $id, 'attributes' => $attributes];
                }

                return new MockResponse(json_encode(['data' => $data, 'total' => count($data)], JSON_PRESERVE_ZERO_FRACTION));
            }));
            $references = new CatalogueExportReferences($client, new SecretCipher('test'));
            $builder = new ShopwareCataloguePayload(dirname(__DIR__, 2), $references);
            $handler = new ExportShopwareCatalogueHandler($manager, $client, $references, new CatalogueExportSelection(), $builder, new IntegrationImportLogger(sys_get_temp_dir().'/connect-export-tests'), new ImportCancellation($database), new InventorySyncOutboxService());
            $preview = $this->plan($manager, $connection, $settings, true);
            $handler($preview);
            $item = $database->fetchAssociative('SELECT * FROM integration_export_items WHERE plan_id = :plan', ['plan' => $preview->planId]);
            self::assertSame([], json_decode($item['issues'], true));
            self::assertCount(10, json_decode($item['payload'], true)['dependencies']);
            self::assertSame([], $writes, 'Planning references must never write to Shopware.');
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $handler($this->publication($manager, $connection, $preview->planId));
            self::assertSame('completed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $preview->planId]));
            self::assertCount(11, $writes);
            self::assertSame('product', end($writes));
            self::assertCount(3, $world['category']);
            self::assertCount(1, $world['product']);
            $remoteId = array_key_first($world['product']);
            self::assertFalse($world['product'][$remoteId]['active']);
            self::assertSame(0, $world['product'][$remoteId]['stock']);
            $queued = [];
            $bus = $this->createStub(MessageBusInterface::class);
            $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$queued): Envelope {
                $queued[] = $message;

                return new Envelope($message);
            });
            $sync = new ShopwareCatalogueSyncService($manager, new CatalogueExportSelection(), $references, $builder, $bus);
            $sync->scan($tenantId, $connectionId);
            self::assertSame([], $queued, 'Unchanged successful products must not be published repeatedly.');
            $product = $manager->find(Product::class, Uuid::fromString($productId));
            $product->updateIdentity('AUTO-EDIT-1', null);
            $manager->flush();
            self::assertSame(1, (int) $database->fetchOne('SELECT COUNT(*) FROM integration_catalogue_dirty WHERE tenant_id = :tenant', ['tenant' => $tenantId]));
            $sync->scan($tenantId, $connectionId);
            self::assertCount(0, $queued, 'New edits wait for the short debounce window.');
            $database->executeStatement("UPDATE integration_catalogue_dirty SET changed_at = CURRENT_TIMESTAMP - INTERVAL '6 seconds' WHERE tenant_id = :tenant", ['tenant' => $tenantId]);
            $database->executeStatement("UPDATE integration_catalogue_sync_state SET scanned_at = CURRENT_TIMESTAMP - INTERVAL '1 minute' WHERE tenant_id = :tenant", ['tenant' => $tenantId]);
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            self::assertSame([$productId], (new CatalogueExportSelection())->matchingIds($database, $connection, $settings, [$productId]));
            self::assertNotSame(
                $database->fetchOne('SELECT source_hash FROM integration_catalogue_publications WHERE product_id = :id', ['id' => $productId]),
                ExportShopwareCatalogueHandler::hash($builder->build($product, $connection, $manager, $settings, false)),
            );
            $sync->scan($tenantId, $connectionId);
            self::assertCount(1, $queued);
            $sync->scan($tenantId, $connectionId);
            self::assertCount(1, $queued, 'Active runs coalesce repeated scan deliveries.');
            $world['product'][$remoteId]['stock'] = 47;
            $world['product'][$remoteId]['active'] = true;
            $handler($queued[0]);
            self::assertSame('completed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $queued[0]->planId]));
            self::assertSame(47, $world['product'][$remoteId]['stock']);
            self::assertTrue($world['product'][$remoteId]['active'], 'Keep status must preserve a merchant activation.');
            self::assertSame('AUTO-EDIT-1', $world['product'][$remoteId]['productNumber']);
            self::assertCount(12, $writes, 'Shared reference identities must not be created again.');
            $product = $manager->find(Product::class, Uuid::fromString($productId));
            $product->updateIdentity('AUTO-EDIT-2', null);
            $manager->flush();
            $database->executeStatement("UPDATE integration_catalogue_dirty SET changed_at = CURRENT_TIMESTAMP - INTERVAL '6 seconds' WHERE tenant_id = :tenant", ['tenant' => $tenantId]);
            $world['product'][$remoteId]['name'] = 'Merchant changed name';
            $database->executeStatement("UPDATE integration_catalogue_sync_state SET scanned_at = CURRENT_TIMESTAMP - INTERVAL '1 minute' WHERE tenant_id = :tenant", ['tenant' => $tenantId]);
            $sync->scan($tenantId, $connectionId);
            self::assertCount(2, $queued);
            $handler($queued[1]);
            self::assertSame('blocked', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $queued[1]->planId]));
            self::assertCount(12, $writes, 'Automatic sync must not overwrite an intervening Shopware edit.');
            $database->executeStatement("UPDATE integration_catalogue_sync_state SET scanned_at = CURRENT_TIMESTAMP - INTERVAL '1 minute' WHERE tenant_id = :tenant", ['tenant' => $tenantId]);
            $sync->scan($tenantId, $connectionId);
            self::assertCount(2, $queued, 'A failed unchanged item uses backoff rather than flooding the queue.');
            $sync->scan((string) Uuid::v7(), $connectionId);
            self::assertCount(2, $queued, 'A connection ID is not a tenant boundary.');
            $tenant = $manager->find(Tenant::class, Uuid::fromString($tenantId));
            $tax = $manager->find(Tax::class, $tax->getId());
            $locale = $manager->find(Locale::class, $locale->getId());
            $currency = $manager->find(Currency::class, $currency->getId());
            for ($index = 0; $index < 30; ++$index) {
                $new = new Product($tenant);
                $new->updateIdentity('BOUNDED-'.$index, null);
                $new->updateReferences($tax, null, null, null, null);
                $new->updatePrices([['currencyId' => (string) $currency->getId(), 'gross' => 12, 'net' => 10, 'linked' => true]], [], []);
                $manager->persist($new);
                $manager->persist(new ProductTranslation($new, $locale, 'New scoped product '.$index));
            }
            $manager->flush();
            $sync->scan($tenantId, $connectionId);
            $database->executeStatement("UPDATE integration_catalogue_dirty SET changed_at = CURRENT_TIMESTAMP - INTERVAL '6 seconds' WHERE tenant_id = :tenant", ['tenant' => $tenantId]);
            $sync->scan($tenantId, $connectionId);
            self::assertCount(3, $queued, 'Matching newly created products are discovered without resaving the scope.');
            self::assertCount(25, json_decode($database->fetchOne('SELECT product_ids FROM integration_export_plans WHERE id = :id', ['id' => $queued[2]->planId]), true));
            self::assertSame(5, (int) $database->fetchOne('SELECT COUNT(*) FROM integration_catalogue_dirty WHERE tenant_id = :tenant', ['tenant' => $tenantId]));
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            $settings['automaticSync'] = false;
            $connection->updateConfiguration(['baseUrl' => 'http://export.test', 'exportSettings' => $settings]);
            $manager->flush();
            $handler($queued[2]);
            self::assertSame('failed', $database->fetchOne('SELECT status FROM integration_export_plans WHERE id = :id', ['id' => $queued[2]->planId]));
            self::assertCount(12, $writes, 'Disabling automatic sync invalidates already queued writes.');
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    private function plan($manager, IntegrationConnection $connection, array $settings, bool $preview): ExportShopwareCatalogue
    {
        $run = new IntegrationImportRun($connection->getTenant(), $connection, 'export_preview');
        $manager->persist($run);
        $manager->flush();
        $plan = (string) Uuid::v7();
        $manager->getConnection()->insert('integration_export_plans', [
            'id' => $plan, 'tenant_id' => (string) $connection->getTenant()->getId(),
            'connection_id' => (string) $connection->getId(), 'run_id' => (string) $run->getId(),
            'settings' => json_encode($settings), 'settings_hash' => CatalogueExportSettings::hash($settings),
        ]);

        return new ExportShopwareCatalogue((string) $connection->getTenant()->getId(), (string) $connection->getId(), $plan, (string) $run->getId(), $preview);
    }

    private function publication($manager, IntegrationConnection $connection, string $plan): ExportShopwareCatalogue
    {
        $run = new IntegrationImportRun($connection->getTenant(), $connection, 'export');
        $manager->persist($run);
        $manager->flush();
        $manager->getConnection()->update('integration_export_plans', [
            'run_id' => (string) $run->getId(),
            'status' => 'publishing',
            'work_phase' => null,
            'work_token' => null,
            'selection_cursor' => null,
            'retry_count' => 0,
        ], ['id' => $plan]);

        return new ExportShopwareCatalogue((string) $connection->getTenant()->getId(), (string) $connection->getId(), $plan, (string) $run->getId(), false);
    }
}
