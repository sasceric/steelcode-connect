<?php

namespace App\Tests\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSecret;
use App\Entity\Tenant;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSelection;
use App\Integration\CatalogueExportSettings;
use App\Integration\CatalogueExportWorkflow;
use App\Integration\ImportCancellation;
use App\Integration\IntegrationImportLogger;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use App\Integration\WooCommerceCatalogueReferences;
use App\Integration\WooCommerceClient;
use App\Integration\WooCommerceSalesRecordIngestor;
use App\Message\ExportShopwareCatalogue;
use App\Message\ExportWooCommerceCatalogue;
use App\Message\SyncWooCommerceSalesConnection;
use App\MessageHandler\SyncWooCommerceSalesConnectionHandler;
use App\Service\PendingSalesOrderProcessor;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class TenantQueueAndCredentialsIsolationTest extends KernelTestCase
{
    public function testForgedExportAndSalesMessagesNeverReachProvidersOrDispatchWork(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $fixtures = [];
            foreach (['shopware', 'woocommerce'] as $provider) {
                foreach (['A', 'B'] as $company) {
                    $tenant = new Tenant($provider.' queue '.$company);
                    $connection = $this->connection($tenant, $provider);
                    $run = new IntegrationImportRun($tenant, $connection, 'export_preview');
                    foreach ([$tenant, $connection, $run] as $entity) {
                        $manager->persist($entity);
                    }
                    $manager->flush();
                    $plan = (string) Uuid::v7();
                    $settings = CatalogueExportSettings::defaults();
                    $database->insert('integration_export_plans', [
                        'id' => $plan,
                        'tenant_id' => (string) $tenant->getId(),
                        'connection_id' => (string) $connection->getId(),
                        'run_id' => (string) $run->getId(),
                        'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                        'settings_hash' => CatalogueExportSettings::hash($settings),
                    ]);
                    $fixtures[$provider][$company] = [(string) $tenant->getId(), (string) $connection->getId(), $plan, (string) $run->getId()];
                }
            }
            $before = $database->fetchAllAssociative('SELECT id, md5(row_to_json(p)::text) AS snapshot FROM integration_export_plans p ORDER BY id');
            $runsBefore = $database->fetchAllAssociative('SELECT id, status FROM integration_import_runs ORDER BY id');
            $http = new MockHttpClient(static function (): never {
                self::fail('A foreign queue message reached a provider or credential-dependent request.');
            });
            $bus = $this->createMock(MessageBusInterface::class);
            $bus->expects(self::never())->method('dispatch');
            $workflow = new CatalogueExportWorkflow(
                $manager,
                new CatalogueExportReferences(new ShopwareClient($http), new SecretCipher('scope')),
                new CatalogueExportSelection(),
                self::getContainer()->get(IntegrationImportLogger::class),
                new ImportCancellation($database),
                $bus,
            );
            $hooks = ['destination' => static function (): never {
                self::fail('A foreign queue message entered provider publication hooks.');
            }];
            foreach (['shopware' => ExportShopwareCatalogue::class, 'woocommerce' => ExportWooCommerceCatalogue::class] as $provider => $messageClass) {
                $a = $fixtures[$provider]['A'];
                $b = $fixtures[$provider]['B'];
                foreach ([
                    [$a[0], $b[1], $b[2], $b[3]],
                    [$a[0], $a[1], $b[2], $a[3]],
                    [$a[0], $a[1], $a[2], $b[3]],
                    [$b[0], $a[1], $a[2], $a[3]],
                    ['not-a-tenant', $a[1], $a[2], $a[3]],
                ] as [$tenant, $connection, $plan, $run]) {
                    $workflow->handle(new $messageClass($tenant, $connection, $plan, $run, true), $provider, $hooks);
                }
            }
            $sales = new SyncWooCommerceSalesConnectionHandler(
                self::getContainer()->get('doctrine'),
                new WooCommerceClient($http),
                self::getContainer()->get(WooCommerceSalesRecordIngestor::class),
                self::getContainer()->get(PendingSalesOrderProcessor::class),
                new SecretCipher('scope'),
                new LockFactory(new FlockStore()),
                new NullLogger(),
            );
            $a = $fixtures['woocommerce']['A'];
            $b = $fixtures['woocommerce']['B'];
            $sales(new SyncWooCommerceSalesConnection($a[0], $b[1]));
            $sales(new SyncWooCommerceSalesConnection($b[0], $a[1]));
            self::assertSame($before, $database->fetchAllAssociative('SELECT id, md5(row_to_json(p)::text) AS snapshot FROM integration_export_plans p ORDER BY id'));
            self::assertSame($runsBefore, $database->fetchAllAssociative('SELECT id, status FROM integration_import_runs ORDER BY id'));
            foreach ([$a, $b] as $fixture) {
                self::assertSame(0, (int) $database->fetchOne('SELECT COUNT(*) FROM integration_sales_sync_cursors WHERE connection_id = :connection', ['connection' => $fixture[1]]));
            }
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    public function testSameProviderUrlDoesNotShareCredentialsOrSettingsCacheBetweenCompanies(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $cipher = new SecretCipher('credential-isolation');
            $connections = [];
            foreach (['A', 'B'] as $company) {
                $tenant = new Tenant('Credentials '.$company);
                $connection = $this->connection($tenant, 'woocommerce');
                $manager->persist($tenant);
                $manager->persist($connection);
                foreach (['consumerKey' => 'key-'.$company, 'consumerSecret' => 'secret-'.$company] as $key => $value) {
                    $encrypted = $cipher->encrypt($value);
                    $manager->persist(new IntegrationSecret($connection, $key, $encrypted['ciphertext'], $encrypted['nonce']));
                }
                $connections[$company] = $connection;
            }
            $manager->flush();
            $calls = ['A' => 0, 'B' => 0];
            $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$calls): MockResponse {
                $company = in_array('Authorization: Basic '.base64_encode('key-A:secret-A'), $options['headers'], true) ? 'A' : 'B';
                self::assertContains('Authorization: Basic '.base64_encode('key-'.$company.':secret-'.$company), $options['headers']);
                ++$calls[$company];

                return new MockResponse(str_contains($url, '/taxes') ? '[]' : json_encode([
                    ['id' => 'company', 'value' => $company],
                ], JSON_THROW_ON_ERROR));
            });
            $references = new CatalogueExportReferences(new ShopwareClient($http), $cipher);
            $woo = new WooCommerceCatalogueReferences(new WooCommerceClient($http), $cipher, new ArrayAdapter());
            foreach ($connections as $company => $connection) {
                self::assertSame(['consumerKey' => 'key-'.$company, 'consumerSecret' => 'secret-'.$company], $references->credentials($connection, $manager));
                self::assertSame($company, $woo->settings($connection, $manager, [])['_woo']['company']);
            }
            self::assertSame(['A' => 4, 'B' => 4], $calls);
            foreach ($connections as $company => $connection) {
                self::assertSame($company, $woo->settings($connection, $manager, [])['_woo']['company']);
            }
            self::assertSame(['A' => 4, 'B' => 4], $calls, 'Each company reuses only its own cached settings.');
            $woo->settings($connections['A'], $manager, [], true);
            self::assertSame(['A' => 8, 'B' => 4], $calls, 'Refreshing A must not evict or reuse B settings.');
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    private function connection(Tenant $tenant, string $provider): IntegrationConnection
    {
        $connection = new IntegrationConnection($tenant, $provider, 'Isolation', ['source', 'channel'], [
            'baseUrl' => 'https://same-provider.example.test',
            'importSettings' => [
                'areas' => ['salesOrders' => true],
                'salesContinuousSync' => true,
                'salesContinuousStartedAt' => '2026-10-01T00:00:00Z',
            ],
        ]);
        $connection->activate('Fixture');

        return $connection;
    }
}
