<?php

namespace App\Tests\Integration;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSecret;
use App\Entity\Manufacturer;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Integration\SecretCipher;
use App\Integration\CatalogueMediaDelivery;
use App\Service\SalesOrderIngestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/** Real firewall/router/controller tests; no registration or external shop calls. */
final class TenantApiIsolationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private array $fixtures = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->manager = self::getContainer()->get('doctrine')->getManager();
        $this->manager->getConnection()->beginTransaction();
        $cipher = self::getContainer()->get(SecretCipher::class);
        foreach (['A', 'B'] as $name) {
            $tenant = new Tenant('Isolation company '.$name);
            $user = new User('isolation-'.bin2hex(random_bytes(8)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $product = new Product($tenant);
            $product->updateIdentity('SHARED-SKU', null);
            $manufacturer = new Manufacturer($tenant);
            $product->updateManufacturer($manufacturer);
            $category = new Category($tenant, 0);
            $warehouse = new Warehouse($tenant, 'default', 'Isolation warehouse '.$name);
            $connection = new IntegrationConnection($tenant, 'shopware', 'Isolation connection '.$name, ['channel'], [
                'baseUrl' => 'https://isolation.example.test',
            ]);
            $connection->activate('Fixture; HTTP must never be attempted.');
            $customer = new Customer($tenant, $connection, 'same-source-customer');
            $customer->updateProfile([
                'email' => 'private-'.$name.'@example.test',
                'firstName' => 'Private '.$name,
                'lastName' => 'Customer',
            ]);
            $order = new SalesOrder($tenant, $connection, 'same-source-order', 'PRIVATE-ORDER-'.$name);
            $run = new IntegrationImportRun($tenant, $connection, 'products');
            $media = new Media($tenant, $tenant->getId().'/fixture.png', 'private-'.$name.'.png', 'png', 4, 'image/png', null);
            $encrypted = $cipher->encrypt('tenant-'.$name.'-secret-must-not-leak');
            $secret = new IntegrationSecret($connection, 'clientSecret', $encrypted['ciphertext'], $encrypted['nonce']);
            $entities = compact('tenant', 'user', 'membership', 'product', 'manufacturer', 'category', 'warehouse', 'connection', 'customer', 'order', 'run', 'media', 'secret');
            foreach ($entities as $entity) {
                $this->manager->persist($entity);
            }
            $this->fixtures[$name] = $entities;
        }
        $this->manager->flush();
        $this->client->loginUser($this->fixtures['A']['user']);
    }

    protected function tearDown(): void
    {
        if (isset($this->manager) && $this->manager->getConnection()->isTransactionActive()) {
            $this->manager->getConnection()->rollBack();
            $this->manager->clear();
        }
        parent::tearDown();
    }

    public function testGuessedForeignReadAndWriteIdsAreRejectedWithoutSideEffects(): void
    {
        $a = $this->ids('A');
        $b = $this->ids('B');
        $before = $this->snapshot();
        $reads = [
            '/products/'.$b['product'],
            '/products/'.$b['product'].'/variants',
            '/products/'.$b['product'].'/channel-publications',
            '/categories/'.$b['category'],
            '/manufacturers/'.$b['manufacturer'],
            '/media/'.$b['media'].'/file',
            '/inventory/products/'.$b['product'],
            '/inventory/warehouses/'.$b['warehouse'].'/stock',
            '/sales/customers/'.$b['customer'],
            '/sales/orders/'.$b['order'],
            '/sales/orders/'.$b['order'].'/pick-task',
            '/integrations/'.$b['connection'].'/configuration',
            '/integrations/'.$b['connection'].'/sales-sync',
            '/integrations/'.$b['connection'].'/sales-channels',
            '/integrations/'.$b['connection'].'/imports',
            '/integrations/'.$a['connection'].'/imports/'.$b['run'].'/logs',
            '/integrations/'.$b['connection'].'/exports/configuration',
            '/integrations/'.$b['connection'].'/exports/plans/latest',
            '/integrations/'.$b['connection'].'/exports/references/local/product',
        ];
        foreach ($reads as $path) {
            $this->request('GET', $path, null, 404);
        }
        $writes = [
            ['PATCH', '/products/'.$b['product'], ['status' => 'active']],
            ['PATCH', '/categories/'.$b['category'], ['active' => false]],
            ['DELETE', '/categories/'.$b['category'], []],
            ['POST', '/categories/'.$b['category'].'/move', ['parentId' => $a['category']]],
            ['PATCH', '/manufacturers/'.$b['manufacturer'], ['website' => 'https://changed.example.test']],
            ['DELETE', '/manufacturers/'.$b['manufacturer'], []],
            ['PATCH', '/inventory/warehouses/'.$b['warehouse'], ['name' => 'Changed']],
            ['POST', '/sales/orders/'.$b['order'].'/pick-task', []],
            ['PATCH', '/sales/orders/'.$b['order'].'/pick-task', []],
            ['POST', '/sales/orders/'.$b['order'].'/shipment-reconciliation', []],
            ['PATCH', '/integrations/'.$b['connection'], ['enabled' => false]],
            ['PATCH', '/integrations/'.$b['connection'].'/configuration', ['areas' => []]],
            ['DELETE', '/integrations/'.$b['connection'], []],
            ['POST', '/integrations/'.$b['connection'].'/test', []],
            ['POST', '/integrations/'.$b['connection'].'/imports/products', []],
            ['POST', '/integrations/'.$b['connection'].'/imports/sales', []],
            ['POST', '/integrations/'.$b['connection'].'/orders/events', []],
            ['PUT', '/integrations/'.$b['connection'].'/exports/configuration', []],
            ['POST', '/integrations/'.$b['connection'].'/exports/preview', []],
            ['POST', '/integrations/'.$b['connection'].'/exports/sync', ['confirmed' => true]],
            ['POST', '/integrations/'.$a['connection'].'/imports/'.$b['run'].'/cancel', []],
        ];
        foreach ($writes as [$method, $path, $body]) {
            $this->request($method, $path, $body, 404);
        }
        self::assertSame($before, $this->snapshot(), 'Denied foreign requests must not change either tenant or dispatch work.');
    }

    public function testListsOptionsSearchAndClientTenantSpoofingStayInTheAuthenticatedTenant(): void
    {
        $a = $this->ids('A');
        $b = $this->ids('B');
        foreach ([
            '/products?limit=25&search=SHARED-SKU',
            '/products?view=options&includeVariants=1&ids='.$a['product'].','.$b['product'],
            '/sales/customers?search=private',
            '/sales/orders?search=PRIVATE-ORDER',
            '/integrations',
            '/media',
            '/inventory/warehouses',
            '/inventory/stock',
            '/integrations/imports/active',
        ] as $path) {
            $this->request('GET', $path, null, 200);
            $content = $this->client->getResponse()->getContent();
            foreach (['product', 'customer', 'order', 'connection', 'media', 'warehouse', 'run'] as $resource) {
                self::assertStringNotContainsString($b[$resource], $content, $path.' leaked a foreign '.$resource);
            }
            self::assertStringNotContainsString('private-B@example.test', $content);
            self::assertStringNotContainsString('tenant-B-secret-must-not-leak', $content);
            self::assertStringNotContainsString('tenant-A-secret-must-not-leak', $content);
            self::assertStringNotContainsString($this->fixtures['B']['secret']->getCiphertext(), $content);
        }
        $this->client->request('GET', '/api/v1/products?limit=25&tenantId='.$b['tenant'], server: ['HTTP_X_TENANT_ID' => $b['tenant']]);
        self::assertResponseStatusCodeSame(200);
        $ids = array_column(json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['products'], 'id');
        self::assertContains($a['product'], $ids);
        self::assertNotContains($b['product'], $ids);
        foreach (['product' => '/products/', 'customer' => '/sales/customers/', 'order' => '/sales/orders/', 'category' => '/categories/', 'manufacturer' => '/manufacturers/'] as $resource => $prefix) {
            $this->request('GET', $prefix.$a[$resource], null, 200);
        }
        $this->client->loginUser($this->fixtures['B']['user']);
        $this->request('GET', '/sales/customers/'.$b['customer'], null, 200);
        self::assertStringContainsString('private-B@example.test', $this->client->getResponse()->getContent());
        $this->request('GET', '/sales/customers/'.$a['customer'], null, 404);
    }

    public function testForeignRelationshipsAndMixedBulkSelectionAreRejectedAtomically(): void
    {
        $a = $this->ids('A');
        $b = $this->ids('B');
        $before = $this->snapshot();
        $this->request('DELETE', '/products', ['ids' => [$a['product'], $b['product']]], 422);
        $this->request('PUT', '/categories/'.$a['category'].'/products', ['productIds' => [$b['product']]], 422);
        $this->request('PUT', '/manufacturers/'.$a['manufacturer'].'/products', ['productIds' => [$b['product']]], 422);
        $this->request('PUT', '/manufacturers/'.$a['manufacturer'].'/products', ['productIds' => [[$a['product']]]], 422);
        $this->request('POST', '/categories/'.$a['category'].'/move', ['parentId' => $b['category']], 422);
        $this->request('POST', '/inventory/counts', [
            'warehouseId' => $a['warehouse'],
            'items' => [['productId' => $b['product'], 'countedQuantity' => 2]],
        ], 404);
        $this->request('POST', '/inventory/counts', [
            'warehouseId' => $b['warehouse'],
            'items' => [['productId' => $a['product'], 'countedQuantity' => 2]],
        ], 404);
        $this->request('PUT', '/integrations/'.$a['connection'].'/exports/configuration', [
            'scope' => 'selected', 'productIds' => [$b['product']],
        ], 422);
        self::assertSame($before, $this->snapshot(), 'Mixed foreign selections must not partially delete, assign or enqueue.');
    }

    public function testUserWithoutMembershipCannotReadBusinessResources(): void
    {
        $user = new User('no-membership-'.bin2hex(random_bytes(8)).'@example.test');
        $user->setPassword('not-used');
        $this->manager->persist($user);
        $this->manager->flush();
        $this->client->loginUser($user);
        foreach (['/products', '/sales/orders', '/sales/customers', '/integrations', '/media', '/inventory/stock'] as $path) {
            $this->request('GET', $path, null, 403);
        }
    }

    public function testAmbiguousMembershipCannotSilentlyChooseACompany(): void
    {
        $this->manager->persist(new TenantMembership($this->fixtures['B']['tenant'], $this->fixtures['A']['user'], 'owner'));
        $this->manager->flush();
        $before = $this->snapshot();
        foreach (['/auth/me', '/products', '/sales/orders', '/sales/customers', '/integrations', '/media', '/inventory/stock', '/catalogue/references'] as $path) {
            $this->request('GET', $path, null, 403);
        }
        $id = (string) $this->fixtures['A']['product']->getId();
        $this->request('PATCH', '/products/'.$id, ['status' => 'active'], 403);
        $this->request('GET', '/products/'.$id.'/channel-publications', null, 403);
        $connection = (string) $this->fixtures['A']['connection']->getId();
        $this->request('GET', '/integrations/'.$connection.'/sales-channels', null, 403);
        $this->request('POST', '/integrations/'.$connection.'/orders/events', [], 403);
        self::assertSame($before, $this->snapshot());
    }

    public function testOwnedMediaRowsCannotPointIntoAnotherCompanyStorage(): void
    {
        $files = new Filesystem();
        $project = self::getContainer()->getParameter('kernel.project_dir');
        $a = $this->fixtures['A'];
        $b = $this->fixtures['B'];
        $directories = [$project.'/var/media/'.$a['tenant']->getId(), $project.'/var/media/'.$b['tenant']->getId()];
        try {
            $files->dumpFile($directories[0].'/fixture.png', 'own-image');
            $files->dumpFile($directories[1].'/fixture.png', 'foreign-image');
            $corrupt = new Media($a['tenant'], $b['tenant']->getId().'/fixture.png', 'corrupt.png', 'png', 13, 'image/png', null);
            $ownAssignment = new ProductMedia($a['tenant'], $a['product'], $a['media'], 0);
            $corruptAssignment = new ProductMedia($a['tenant'], $a['product'], $corrupt, 1);
            $foreignAssignment = new ProductMedia($a['tenant'], $a['product'], $b['media'], 2);
            foreach ([$corrupt, $ownAssignment, $corruptAssignment, $foreignAssignment] as $entity) {
                $this->manager->persist($entity);
            }
            $this->manager->flush();
            $this->client->request('GET', '/api/v1/media/'.$a['media']->getId().'/file');
            self::assertResponseStatusCodeSame(200);
            $this->assertPrivateMediaCachePolicy();
            self::assertResponseHeaderSame('x-content-type-options', 'nosniff');
            $this->client->request('GET', '/api/v1/products/'.$a['product']->getId().'/media/'.$ownAssignment->getId().'/file');
            self::assertResponseStatusCodeSame(200);
            $this->assertPrivateMediaCachePolicy();
            self::assertResponseHeaderSame('content-security-policy', "default-src 'none'; sandbox");
            $this->request('GET', '/media/'.$corrupt->getId().'/file', null, 404);
            foreach ([$corruptAssignment, $foreignAssignment] as $assignment) {
                $this->request('GET', '/products/'.$a['product']->getId().'/media/'.$assignment->getId().'/file', null, 404);
            }
            $url = self::getContainer()->get(CatalogueMediaDelivery::class)->url($a['connection'], $corrupt);
            $this->client->request('GET', parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY));
            self::assertResponseStatusCodeSame(404, 'Even a valid signed capability cannot authorize a foreign storage path.');
        } finally {
            // These are UUID directories created only by this rollback fixture.
            $files->remove($directories);
        }
    }

    public function testValidManufacturerSelectionCanBeReplacedAndCleared(): void
    {
        $a = $this->ids('A');
        $second = new Product($this->fixtures['A']['tenant']);
        $second->updateIdentity('SECOND-SKU', null);
        $this->manager->persist($second);
        $this->manager->flush();
        $this->request('PUT', '/manufacturers/'.$a['manufacturer'].'/products', ['productIds' => [(string) $second->getId()]], 200);
        $database = $this->manager->getConnection();
        self::assertNull($database->fetchOne('SELECT manufacturer_id FROM products WHERE id = :id', ['id' => $a['product']]));
        self::assertSame($a['manufacturer'], $database->fetchOne('SELECT manufacturer_id FROM products WHERE id = :id', ['id' => (string) $second->getId()]));
        $this->request('PUT', '/manufacturers/'.$a['manufacturer'].'/products', ['productIds' => []], 200);
        self::assertNull($database->fetchOne('SELECT manufacturer_id FROM products WHERE id = :id', ['id' => (string) $second->getId()]));
        self::assertSame((string) $this->fixtures['B']['manufacturer']->getId(), $database->fetchOne(
            'SELECT manufacturer_id FROM products WHERE id = :id', ['id' => (string) $this->fixtures['B']['product']->getId()],
        ));
    }

    public function testOverlappingSourceIdsAndSkusImportIntoTheirOwnCompanyOnly(): void
    {
        $orders = self::getContainer()->get(SalesOrderIngestionService::class);
        $database = $this->manager->getConnection();
        $movementCount = (int) $database->fetchOne('SELECT COUNT(*) FROM inventory_movements');
        foreach (['A', 'B'] as $name) {
            $fixture = $this->fixtures[$name];
            $customer = $orders->ingestCustomer(
                $fixture['connection'],
                ['externalId' => 'same-import-customer', 'email' => 'import-'.$name.'@example.test'],
                ['externalId' => 'same-address', 'street' => 'Company '.$name.' address'],
                ['externalId' => 'same-address', 'street' => 'Company '.$name.' address'],
                $this->manager,
            );
            $event = [
                'type' => 'placed',
                'externalId' => 'same-import-order',
                'externalNumber' => 'SAME-100',
                'customer' => ['externalId' => 'same-import-customer', 'email' => 'import-'.$name.'@example.test'],
                'lines' => [[
                    'externalLineId' => 'same-import-line',
                    'sku' => 'SHARED-SKU',
                    'name' => 'Same source product',
                    'quantity' => 2,
                ]],
            ];
            $order = $orders->ingest($fixture['connection'], $event, $this->manager, false);
            $again = $orders->ingest($fixture['connection'], $event, $this->manager, false);
            self::assertSame((string) $order->getId(), (string) $again->getId());
            self::assertSame((string) $fixture['tenant']->getId(), (string) $order->getTenant()->getId());
            self::assertSame((string) $customer->getId(), (string) $order->getCustomer()->getId());
            self::assertSame('Company '.$name.' address', $customer->getAddresses()->first()->getStreet());
            self::assertCount(1, $order->getItems());
            self::assertSame((string) $fixture['product']->getId(), (string) $order->getItems()->first()->getProduct()->getId());
            self::assertSame('historical', $order->getStatus());
            self::assertSame(1, (int) $database->fetchOne(
                'SELECT COUNT(*) FROM sales_orders WHERE tenant_id = :tenant AND connection_id = :connection AND external_id = :external',
                ['tenant' => (string) $fixture['tenant']->getId(), 'connection' => (string) $fixture['connection']->getId(), 'external' => 'same-import-order'],
            ));
        }
        self::assertSame($movementCount, (int) $database->fetchOne('SELECT COUNT(*) FROM inventory_movements'));
    }

    private function ids(string $name): array
    {
        $ids = [];
        foreach ($this->fixtures[$name] as $key => $entity) {
            if (method_exists($entity, 'getId')) {
                $ids[$key] = (string) $entity->getId();
            }
        }

        return $ids;
    }

    private function assertPrivateMediaCachePolicy(): void
    {
        $headers = $this->client->getResponse()->headers;
        self::assertTrue($headers->hasCacheControlDirective('private'), (string) $headers->get('cache-control'));
        self::assertTrue($headers->hasCacheControlDirective('no-store'), (string) $headers->get('cache-control'));
        self::assertFalse($headers->hasCacheControlDirective('public'));
    }

    private function request(string $method, string $path, ?array $body, int $status): void
    {
        $this->client->jsonRequest($method, '/api/v1'.$path, $body ?? []);
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $detail = is_array($content) ? ($content['detail'] ?? $content['message'] ?? '') : '';
        self::assertSame($status, $this->client->getResponse()->getStatusCode(), $method.' '.$path.' '.$detail);
        self::assertStringNotContainsString('tenant-B-secret-must-not-leak', $this->client->getResponse()->getContent());
    }

    private function snapshot(): array
    {
        $database = $this->manager->getConnection();
        $snapshot = [];
        foreach (['products', 'categories', 'manufacturers', 'warehouses', 'media', 'customers', 'sales_orders', 'integration_connections', 'integration_import_runs', 'integration_export_plans', 'inventory_counts'] as $table) {
            $snapshot[$table] = $database->fetchAllAssociative(
                'SELECT id, md5(row_to_json(t)::text) AS checksum FROM '.$table.' t WHERE tenant_id IN (:a, :b) ORDER BY id',
                ['a' => (string) $this->fixtures['A']['tenant']->getId(), 'b' => (string) $this->fixtures['B']['tenant']->getId()],
            );
        }
        $snapshot['queue'] = $database->fetchAllAssociative('SELECT id, body FROM messenger_messages ORDER BY id');

        return $snapshot;
    }
}
