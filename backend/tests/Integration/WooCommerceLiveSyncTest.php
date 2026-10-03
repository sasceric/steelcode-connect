<?php

namespace App\Tests\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\IntegrationSecret;
use App\Entity\InventoryLevel;
use App\Entity\InventoryMovement;
use App\Entity\InventorySyncOutbox;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Integration\SecretCipher;
use App\Integration\WooCommerceClient;
use App\Integration\WooCommerceSalesRecordIngestor;
use App\Integration\WooCommerceStockPublisher;
use App\Message\SyncWooCommerceSalesConnection;
use App\MessageHandler\SyncWooCommerceSalesConnectionHandler;
use App\Service\InventoryService;
use App\Service\PendingSalesOrderProcessor;
use App\Service\SalesOrderIngestionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class WooCommerceLiveSyncTest extends KernelTestCase
{
    private function fixture(): array
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $manager->getConnection()->beginTransaction();
        $tenant = new Tenant('Woo live regression');
        $product = new Product($tenant);
        $product->updateIdentity('LIVE-' . bin2hex(random_bytes(4)), null);
        $startedAt = new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC'));
        $connection = new IntegrationConnection(
            $tenant,
            'woocommerce',
            'Woo live',
            ['source', 'channel'],
            [
                'baseUrl' => 'https://woo.test',
                'importSettings' => [
                    'areas' => ['salesOrders' => true, 'salesCustomers' => false],
                    'salesContinuousSync' => true,
                    'salesContinuousStartedAt' => $startedAt->format(\DateTimeInterface::ATOM),
                    'stockAuthority' => 'connect',
                ],
            ],
        );
        $connection->activate('Connected');
        $cipher = new SecretCipher('woo-live-test');
        foreach ([$tenant, $product, $connection] as $entity) {
            $manager->persist($entity);
        }
        foreach (['consumerKey' => 'key', 'consumerSecret' => 'secret'] as $key => $value) {
            $encrypted = $cipher->encrypt($value);
            $manager->persist(
                new IntegrationSecret(
                    $connection,
                    $key,
                    $encrypted['ciphertext'],
                    $encrypted['nonce'],
                ),
            );
        }
        $manager->persist(
            new IntegrationEntityMapping(
                $tenant,
                $connection,
                'product',
                '10',
                $product->getId(),
            ),
        );
        $manager->flush();
        $inventory = self::getContainer()->get(InventoryService::class);
        $warehouse = $inventory->defaultWarehouse($tenant, $manager);
        $inventory->setStock(
            $tenant,
            $warehouse,
            $product,
            '100.0000',
            null,
            'Test opening stock',
            $manager,
        );
        $manager->flush();
        return [
            $manager,
            $tenant,
            $connection,
            $product,
            $warehouse,
            $cipher,
            $startedAt,
        ];
    }

    private function source(
        int $id,
        \DateTimeImmutable $created,
    ): array
    {
        return [
            'id' => $id,
            'currency' => 'EUR',
            'status' => 'processing',
            'date_created_gmt' => $created->format('Y-m-d\TH:i:s'),
            'line_items' => [
                [
                    'id' => $id,
                    'product_id' => 10,
                    'sku' => 'wrong-source-sku',
                    'name' => 'Test item',
                    'quantity' => 2,
                    'total' => '20',
                ],
            ],
            'total' => '20',
        ];
    }

    private function handler(
        EntityManagerInterface $manager,
        WooCommerceClient $client,
        SecretCipher $cipher,
    ): SyncWooCommerceSalesConnectionHandler
    {
        return new SyncWooCommerceSalesConnectionHandler(
            self::getContainer()->get('doctrine'),
            $client,
            self::getContainer()->get(WooCommerceSalesRecordIngestor::class),
            self::getContainer()->get(PendingSalesOrderProcessor::class),
            $cipher,
            new LockFactory(new FlockStore()),
            new NullLogger(),
        );
    }

    public function testPagingReplayEditsManualShippingCancellationAndTenantBoundary(): void
    {
        [
            $manager,
            $tenant,
            $connection,
            $product,
            $warehouse,
            $cipher,
            $startedAt,
        ] = $this->fixture();
        $database = $manager->getConnection();
        try {
            $sources = [];
            for ($id = 1; $id <= 26; ++$id) {
                $sources[] = $this->source($id, $startedAt->modify('+10 seconds'));
            }
            $sources[] = $this->source(27, $startedAt->modify('-1 day'));
            $pages = [];
            $http = new MockHttpClient(
                function (
                    string $method,
                    string $url,
                ) use (&$sources, &$pages): MockResponse {
                    parse_str(parse_url($url, PHP_URL_QUERY), $query);
                    self::assertSame('25', $query['per_page']);
                    self::assertSame('true', $query['dates_are_gmt']);
                    self::assertArrayHasKey('modified_after', $query);
                    self::assertArrayHasKey('modified_before', $query);
                    $page = (int) $query['page'];
                    $pages[] = $page;
                    return new MockResponse(
                        json_encode(array_slice($sources, ($page - 1) * 25, 25), JSON_THROW_ON_ERROR),
                        ['response_headers' => ['X-WP-Total: 27', 'X-WP-TotalPages: 2']],
                    );
                },
            );
            $handler = $this->handler($manager, new WooCommerceClient($http), $cipher);
            $message = new SyncWooCommerceSalesConnection((string) $tenant->getId(), (string) $connection->getId());
            $handler(
                new SyncWooCommerceSalesConnection((string) (new Tenant('Other tenant'))->getId(), $message->connectionId),
            );
            self::assertSame([], $pages);
            $handler($message);
            self::assertSame([1, 2], $pages);
            self::assertSame(26, $manager->getRepository(SalesOrder::class)->count(['connection' => $connection]));
            $level = $manager->getRepository(InventoryLevel::class)->findOneBy(['warehouse' => $warehouse, 'product' => $product]);
            self::assertSame('52.0000', $level->getReservedQuantity());
            $movementCount = $manager->getRepository(InventoryMovement::class)->count(['tenant' => $tenant]);
            $event = $manager->getRepository(InventorySyncOutbox::class)->findOneBy(['tenant' => $tenant, 'product' => $product]);
            $eventUpdatedAt = $event->getUpdatedAt();
            $handler($message);
            self::assertSame(
                $movementCount,
                $manager->getRepository(InventoryMovement::class)->count(['tenant' => $tenant]),
            );
            $event = $manager->getRepository(InventorySyncOutbox::class)->findOneBy(['tenant' => $tenant, 'product' => $product]);
            self::assertEquals(
                $eventUpdatedAt,
                $event->getUpdatedAt(),
                'Overlap replay must not perpetually postpone stock publication.',
            );
            $sources[0]['line_items'][0]['quantity'] = 3;
            $sources[0]['status'] = 'completed';
            $handler($message);
            $level = $manager->getRepository(InventoryLevel::class)->findOneBy(['warehouse' => $warehouse, 'product' => $product]);
            self::assertSame('53.0000', $level->getReservedQuantity());
            self::assertSame('100.0000', $level->getQuantity(), 'Woo completed does not prove physical shipment.');
            $order = $manager->getRepository(SalesOrder::class)->findOneBy(['connection' => $connection, 'externalId' => '1']);
            self::assertTrue($order->needsShipmentReconciliation());
            $sales = self::getContainer()->get(SalesOrderIngestionService::class);
            $sales->reconcileManualShipment($order, ['product:1' => '1'], $manager);
            self::assertSame('partially_fulfilled', $order->getStatus());
            $sales->reconcileManualShipment($order, ['product:1' => '3'], $manager);
            self::assertSame('fulfilled', $order->getStatus());
            $sources[0]['status'] = 'refunded';
            $sources[1]['status'] = 'cancelled';
            $handler($message);
            $level = $manager->getRepository(InventoryLevel::class)->findOneBy(['warehouse' => $warehouse, 'product' => $product]);
            self::assertSame('97.0000', $level->getQuantity(), 'Financial refund must not restock a shipment.');
            self::assertSame('48.0000', $level->getReservedQuantity());
            $order = $manager->getRepository(SalesOrder::class)->findOneBy(['connection' => $connection, 'externalId' => '1']);
            self::assertSame('refunded', $order->getSourceStatus());
            self::assertSame('fulfilled', $order->getStatus());
            $cancelled = $manager->getRepository(SalesOrder::class)->findOneBy(['connection' => $connection, 'externalId' => '2']);
            self::assertSame('cancelled', $cancelled->getStatus());
            $cursor = $manager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy(['connection' => $connection]);
            self::assertGreaterThan($startedAt, $cursor->getLastSyncedAt());
            self::assertNull($cursor->getLastError());
        } finally {
            $database->rollBack();
        }
    }

    public function testBadRecordRetainsCheckpointForRetry(): void
    {
        [
            $manager,
            $tenant,
            $connection,
            $product,
            $warehouse,
            $cipher,
            $startedAt,
        ] = $this->fixture();
        $database = $manager->getConnection();
        try {
            $bad = $this->source(90, $startedAt->modify('+10 seconds'));
            $bad['line_items'][0]['quantity'] = -1;
            $http = new MockHttpClient(
                new MockResponse(
                    json_encode([$bad], JSON_THROW_ON_ERROR),
                    ['response_headers' => ['X-WP-Total: 1', 'X-WP-TotalPages: 1']],
                ),
            );
            try {
                $this->handler($manager, new WooCommerceClient($http), $cipher)(
                    new SyncWooCommerceSalesConnection((string) $tenant->getId(), (string) $connection->getId()),
                );
                self::fail('The malformed source order should fail the scan.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('checkpoint was not advanced', $exception->getMessage());
            }
            $cursor = $manager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy(['connection' => $connection]);
            self::assertEquals($cursor->getStartedAt(), $cursor->getLastSyncedAt());
            self::assertNotNull($cursor->getLastError());
            self::assertSame(0, $manager->getRepository(SalesOrder::class)->count(['connection' => $connection]));
        } finally {
            $database->rollBack();
        }
    }

    public function testStockTargetsExactVariationAndNeverEnablesUnmanagedStock(): void
    {
        [
            $manager,
            $tenant,
            $connection,
            $product,
        ] = $this->fixture();
        $database = $manager->getConnection();
        try {
            $variant = new Product($tenant);
            $variant->updateIdentity('VARIANT-' . bin2hex(random_bytes(4)), null);
            $variant->makeChildOf(
                $product,
                $variant->getSku(),
                null,
                [],
            );
            $manager->persist($variant);
            $manager->persist(
                new IntegrationEntityMapping(
                    $tenant,
                    $connection,
                    'product',
                    '11',
                    $variant->getId(),
                ),
            );
            $manager->flush();
            $requests = [];
            $orderSource = $this->source(91, new \DateTimeImmutable('-10 seconds', new \DateTimeZone('UTC')));
            $orderSource['line_items'][0]['variation_id'] = 11;
            $order = self::getContainer()->get(WooCommerceSalesRecordIngestor::class)->order(
                $orderSource,
                $connection,
                $manager,
                true,
            );
            self::assertSame((string) $variant->getId(), (string) $order->getItems()->first()->getProduct()->getId());
            $managed = true;
            $http = new MockHttpClient(
                function (
                    string $method,
                    string $url,
                    array $options,
                ) use (&$requests, &$managed): MockResponse {
                    self::assertSame('/wp-json/wc/v3/products/10/variations/11', parse_url($url, PHP_URL_PATH));
                    $requests[] = $method;
                    if ($method === 'PUT') {
                        self::assertSame(['stock_quantity' => 8], json_decode($options['body'], true));
                    }
                    return new MockResponse(
                        json_encode(
                            ['id' => 11, 'manage_stock' => $managed, 'stock_quantity' => $method === 'PUT' ? 8 : 10],
                            JSON_THROW_ON_ERROR,
                        ),
                    );
                },
            );
            $publisher = new WooCommerceStockPublisher(new WooCommerceClient($http));
            $secrets = ['consumerKey' => 'key', 'consumerSecret' => 'secret'];
            $publisher->publish(
                $connection,
                $variant,
                '11',
                '8.0000',
                $secrets,
                $manager,
            );
            self::assertSame(['GET', 'PUT'], $requests);
            $managed = false;
            $publisher->publish(
                $connection,
                $variant,
                '11',
                '8.0000',
                $secrets,
                $manager,
            );
            self::assertSame(['GET', 'PUT', 'GET'], $requests);
            $managed = 'parent';
            try {
                $publisher->publish(
                    $connection,
                    $variant,
                    '11',
                    '8.0000',
                    $secrets,
                    $manager,
                );
                self::fail('Inherited stock pools must not be overwritten.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('pool-aware', $exception->getMessage());
            }
            $managed = true;
            try {
                $publisher->publish(
                    $connection,
                    $variant,
                    '11',
                    '8.5000',
                    $secrets,
                    $manager,
                );
                self::fail('Fractional stock must not be rounded.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('whole-unit', $exception->getMessage());
            }
        } finally {
            $database->rollBack();
        }
    }
}
