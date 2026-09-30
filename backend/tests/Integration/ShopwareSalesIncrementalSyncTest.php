<?php

namespace App\Tests\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\IntegrationSecret;
use App\Entity\InventoryLevel;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use App\Integration\ShopwareSalesMapper;
use App\Integration\ShopwareSalesRecordIngestor;
use App\Message\SyncShopwareSalesConnection;
use App\MessageHandler\SyncShopwareSalesConnectionHandler;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use App\Service\SalesOrderAllocationService;
use App\Service\SalesOrderIngestionService;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class ShopwareSalesIncrementalSyncTest extends KernelTestCase
{
    public function testPagedIncrementalSyncReservesNewOrderOnlyOnce(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Incremental Sales sync test');
            $sku = 'SYNC-SKU-'.bin2hex(random_bytes(4));
            $product = new Product($tenant);
            $product->updateIdentity($sku, null);
            $startedAt = (new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
            $connection = new IntegrationConnection(
                $tenant,
                'shopware',
                'Incremental Shopware '.bin2hex(random_bytes(4)),
                ['source', 'channel'],
                [
                    'baseUrl' => 'https://shop.example.test',
                    'importSettings' => [
                        'areas' => ['salesOrders' => true, 'salesCustomers' => false],
                        'salesContinuousSync' => true,
                        'salesContinuousStartedAt' => $startedAt,
                    ],
                ],
            );
            $connection->activate('Connected');
            $entityManager->persist($tenant);
            $entityManager->persist($product);
            $entityManager->persist($connection);

            $cipher = new SecretCipher('test-secret');
            foreach (['accessKeyId' => 'key', 'secretAccessKey' => 'secret'] as $key => $value) {
                $encrypted = $cipher->encrypt($value);
                $entityManager->persist(new IntegrationSecret(
                    $connection,
                    $key,
                    $encrypted['ciphertext'],
                    $encrypted['nonce'],
                ));
            }
            $entityManager->flush();

            $inventory = new InventoryService(new InventorySyncOutboxService());
            $warehouse = $inventory->defaultWarehouse($tenant, $entityManager);
            $inventory->setStock($tenant, $warehouse, $product, '10.0000', null, 'Opening balance', $entityManager);
            $entityManager->flush();

            $order = [
                'id' => 'sync-order',
                'attributes' => [
                    'orderNumber' => 'SYNC-100',
                    'orderDateTime' => $startedAt,
                    'lineItems' => [[
                        'id' => 'sync-line',
                        'attributes' => [
                            'type' => 'product',
                            'label' => 'Test item',
                            'quantity' => 2,
                            'payload' => ['productNumber' => $sku],
                        ],
                    ]],
                ],
            ];
            $http = new MockHttpClient(static function (string $method, string $url, array $options) use ($order): MockResponse {
                if (str_ends_with($url, '/api/oauth/token')) {
                    return new MockResponse(json_encode(['access_token' => 'token'], JSON_THROW_ON_ERROR));
                }

                $criteria = json_decode($options['body'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
                $createdOrderScan = str_ends_with($url, '/api/search/order')
                    && ($criteria['filter'][0]['field'] ?? null) === 'createdAt';

                return new MockResponse(json_encode([
                    'data' => $createdOrderScan ? [$order] : [],
                    'total' => $createdOrderScan ? 1 : 0,
                ], JSON_THROW_ON_ERROR));
            });
            $mapper = new ShopwareSalesMapper();
            $ingestion = new SalesOrderIngestionService(new SalesOrderAllocationService($inventory));
            $handler = new SyncShopwareSalesConnectionHandler(
                $entityManager,
                new ShopwareClient($http),
                $mapper,
                new ShopwareSalesRecordIngestor($mapper, $ingestion),
                $ingestion,
                $cipher,
                new LockFactory(new FlockStore()),
                new NullLogger(),
                self::$kernel->getContainer()->get('doctrine'),
            );

            $message = new SyncShopwareSalesConnection($connection->getId()->toRfc4122());
            $handler($message);
            $handler($message);

            $currentConnection = $entityManager->find(IntegrationConnection::class, $connection->getId());
            $salesOrder = $entityManager->getRepository(SalesOrder::class)->findOneBy([
                'connection' => $currentConnection,
                'externalId' => 'sync-order',
            ]);
            self::assertInstanceOf(SalesOrder::class, $salesOrder);
            self::assertSame('reserved', $salesOrder->getStatus());
            $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                'warehouse' => $warehouse,
                'product' => $product,
            ]);
            self::assertSame('2.0000', $level?->getReservedQuantity());
            $cursor = $entityManager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy([
                'connection' => $currentConnection,
            ]);
            self::assertInstanceOf(IntegrationSalesSyncCursor::class, $cursor);
            self::assertGreaterThan(new \DateTimeImmutable($startedAt), $cursor->getLastSyncedAt());
        } finally {
            $database->rollBack();
        }
    }
}
