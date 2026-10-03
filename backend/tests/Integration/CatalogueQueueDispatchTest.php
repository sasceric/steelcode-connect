<?php

namespace App\Tests\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\Product;
use App\Entity\Tenant;
use App\Message\QueueShopwareCatalogueSync;
use App\Message\ReconcileShopwareCatalogue;
use App\MessageHandler\QueueShopwareCatalogueSyncHandler;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class CatalogueQueueDispatchTest extends KernelTestCase
{
    public function testDebounceLeaseTenantScopeAndActiveRunGuard(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenants = [new Tenant('Queue A'), new Tenant('Queue B')];
            $connections = [];
            foreach ($tenants as $tenant) {
                $connection = new IntegrationConnection($tenant, 'shopware', 'Queue test', ['channel'], [
                    'exportSettings' => ['automaticSync' => true],
                ]);
                $connection->activate('Test');
                $product = new Product($tenant);
                $manager->persist($tenant);
                $manager->persist($connection);
                $manager->persist($product);
                $connections[] = $connection;
                $manager->flush();
                $database->executeStatement(<<<'SQL'
INSERT INTO integration_catalogue_dirty (tenant_id, connection_id, product_id, revision)
VALUES (:tenant, :connection, :product, :revision)
ON CONFLICT (tenant_id, connection_id, product_id) DO UPDATE SET changed_at = CURRENT_TIMESTAMP, first_changed_at = CURRENT_TIMESTAMP
SQL, [
                    'tenant' => (string) $tenant->getId(),
                    'connection' => (string) $connection->getId(),
                    'product' => (string) $product->getId(),
                    'revision' => (string) Uuid::v7(),
                ]);
            }
            $queued = [];
            $bus = $this->createStub(MessageBusInterface::class);
            $bus->method('dispatch')->willReturnCallback(static function (object $message, array $stamps = []) use (&$queued): Envelope {
                $queued[] = $message;

                return new Envelope($message, $stamps);
            });
            $handler = new QueueShopwareCatalogueSyncHandler($manager, new NullLogger(), $bus);
            $handler(new QueueShopwareCatalogueSync());
            self::assertSame([], $queued, 'A first reconciliation must not bypass fresh-edit debounce.');
            $database->executeStatement(
                "UPDATE integration_catalogue_dirty SET changed_at = CURRENT_TIMESTAMP - INTERVAL '6 seconds' WHERE tenant_id IN (:a, :b)",
                ['a' => (string) $tenants[0]->getId(), 'b' => (string) $tenants[1]->getId()],
            );
            $handler(new QueueShopwareCatalogueSync());
            self::assertCount(2, $queued);
            foreach ($queued as $message) {
                self::assertInstanceOf(ReconcileShopwareCatalogue::class, $message);
                self::assertSame($message->tenantId, $database->fetchOne(
                    'SELECT tenant_id FROM integration_connections WHERE id = :id',
                    ['id' => $message->connectionId],
                ));
            }
            $handler(new QueueShopwareCatalogueSync());
            self::assertCount(2, $queued, 'The lease coalesces repeated scheduler ticks.');
            $database->executeStatement(
                'UPDATE integration_catalogue_sync_state SET queued_until = NULL WHERE tenant_id IN (:a, :b)',
                ['a' => (string) $tenants[0]->getId(), 'b' => (string) $tenants[1]->getId()],
            );
            $manager->persist(new IntegrationImportRun($tenants[0], $connections[0], 'products'));
            $manager->flush();
            $handler(new QueueShopwareCatalogueSync());
            self::assertCount(3, $queued);
            self::assertSame((string) $tenants[1]->getId(), $queued[2]->tenantId, 'An active import holds only its own connection.');
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }
}
