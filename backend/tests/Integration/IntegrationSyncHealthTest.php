<?php

namespace App\Tests\Integration;

use App\Command\CatalogueSyncStatusCommand;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\InventorySyncOutbox;
use App\Entity\Product;
use App\Entity\Tenant;
use App\Service\IntegrationSyncHealth;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class IntegrationSyncHealthTest extends KernelTestCase
{
    public static function providers(): iterable
    {
        yield 'Shopware' => ['shopware'];
        yield 'WooCommerce' => ['woocommerce'];
    }

    #[DataProvider('providers')]
    public function testHealthSignalsRemainTenantScopedAndRecover(string $provider): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Health '.$provider);
            $otherTenant = new Tenant('Foreign health');
            $connection = new IntegrationConnection($tenant, $provider, 'Health fixture', ['channel'], [
                'exportSettings' => ['automaticSync' => true],
                'sensitiveCredential' => 'must-not-appear',
            ]);
            $connection->activate('Fixture');
            $product = new Product($tenant);
            $foreignProduct = new Product($otherTenant);
            $foreignEvent = new InventorySyncOutbox($otherTenant, $foreignProduct);
            $foreignEvent->markFailed('Private foreign error payload');
            foreach ([$tenant, $otherTenant, $connection, $product, $foreignProduct, $foreignEvent] as $entity) {
                $manager->persist($entity);
            }
            $manager->flush();
            $tenantId = (string) $tenant->getId();
            $connectionId = (string) $connection->getId();
            $reader = new IntegrationSyncHealth();
            $report = $reader->read($database, $tenantId, $connectionId);
            self::assertSame('ok', $report['health']);
            self::assertSame($provider, $report['connection']['connector_key']);
            self::assertSame(0, $report['stockOutbox']['failed']);
            self::assertStringNotContainsString('must-not-appear', json_encode($report));
            self::assertStringNotContainsString('Private foreign', json_encode($report));

            $tester = new CommandTester(new CatalogueSyncStatusCommand($manager, $reader));
            self::assertSame(0, $tester->execute([
                'tenant-id' => $tenantId,
                'connection-id' => $connectionId,
                '--check' => true,
            ]));
            self::assertSame(1, $tester->execute([
                'tenant-id' => (string) $otherTenant->getId(),
                'connection-id' => $connectionId,
            ]), 'A guessed connection ID cannot access another tenant diagnostics.');
            self::assertStringNotContainsString($connectionId, $tester->getDisplay());
            self::assertSame(2, $tester->execute([
                'tenant-id' => $tenantId,
                'connection-id' => $connectionId,
                '--backlog-seconds' => '0',
            ]));

            $run = new IntegrationImportRun($tenant, $connection, 'export_sync');
            $manager->persist($run);
            $manager->flush();
            $database->executeStatement(<<<'SQL'
INSERT INTO integration_catalogue_dirty (tenant_id, connection_id, product_id, revision, first_changed_at)
VALUES (:tenant, :connection, :product, :revision, CURRENT_TIMESTAMP - INTERVAL '3 minutes')
ON CONFLICT (tenant_id, connection_id, product_id) DO UPDATE SET first_changed_at = EXCLUDED.first_changed_at
SQL, [
                'tenant' => $tenantId,
                'connection' => $connectionId,
                'product' => (string) $product->getId(),
                'revision' => (string) Uuid::v7(),
            ]);
            $database->executeStatement(
                "UPDATE integration_import_runs SET updated_at = (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') - INTERVAL '20 minutes' WHERE tenant_id = :tenant AND id = :run",
                ['tenant' => $tenantId, 'run' => (string) $run->getId()],
            );
            self::assertSame(2, $tester->execute([
                'tenant-id' => $tenantId,
                'connection-id' => $connectionId,
                '--check' => true,
            ]));
            $report = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('warning', $report['health']);
            self::assertEqualsCanonicalizing(
                ['catalogue_backlog_age', 'queued_run_age'],
                array_column($report['alerts'], 'code'),
            );
            $database->executeStatement(
                "UPDATE integration_import_runs SET status = 'running' WHERE tenant_id = :tenant AND id = :run",
                ['tenant' => $tenantId, 'run' => (string) $run->getId()],
            );
            self::assertContains('stale_run_heartbeat', array_column($reader->read($database, $tenantId, $connectionId)['alerts'], 'code'));

            $connection->updateConfiguration([
                'exportSettings' => ['automaticSync' => true],
                'importSettings' => ['salesContinuousSync' => true],
            ]);
            $event = new InventorySyncOutbox($tenant, $product);
            $event->markFailed('Sensitive provider response must not be returned');
            $manager->persist($event);
            $manager->flush();
            $database->executeStatement(
                "UPDATE integration_import_runs SET status = 'completed', failed_items = 1 WHERE tenant_id = :tenant AND id = :run",
                ['tenant' => $tenantId, 'run' => (string) $run->getId()],
            );
            self::assertSame(1, $tester->execute([
                'tenant-id' => $tenantId,
                'connection-id' => $connectionId,
                '--check' => true,
            ]));
            $report = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertContains('latest_run_requires_attention', array_column($report['alerts'], 'code'));
            self::assertContains('sales_checkpoint_not_fresh', array_column($report['alerts'], 'code'));
            self::assertContains('tenant_stock_outbox_failed', array_column($report['alerts'], 'code'));
            self::assertStringNotContainsString('Sensitive provider', $tester->getDisplay());

            $connection->updateConfiguration(['exportSettings' => ['automaticSync' => true]]);
            $event->markDispatched();
            $manager->flush();
            $database->delete('integration_catalogue_dirty', ['tenant_id' => $tenantId]);
            $database->executeStatement(
                'UPDATE integration_import_runs SET failed_items = 0 WHERE tenant_id = :tenant AND id = :run',
                ['tenant' => $tenantId, 'run' => (string) $run->getId()],
            );
            self::assertSame('ok', $reader->read($database, $tenantId, $connectionId)['health']);

            $connection->updateConfiguration([
                'exportSettings' => ['automaticSync' => true],
                'importSettings' => ['salesContinuousSync' => true],
            ]);
            $cursor = new IntegrationSalesSyncCursor($connection, new \DateTimeImmutable('-1 hour'));
            $cursor->advance(new \DateTimeImmutable());
            $manager->persist($cursor);
            $manager->flush();
            $report = $reader->read($database, $tenantId, $connectionId);
            self::assertSame('ok', $report['health'], 'UTC ORM checkpoints must not appear two hours old under a non-UTC database session.');
            self::assertLessThan(30, $report['salesCheckpoint']['age_seconds']);

            $event->queue();
            $manager->flush();
            $database->executeStatement(
                "UPDATE inventory_sync_outbox SET updated_at = (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') - INTERVAL '3 minutes' WHERE tenant_id = :tenant AND product_id = :product",
                ['tenant' => $tenantId, 'product' => (string) $product->getId()],
            );
            self::assertContains('tenant_stock_outbox_age', array_column($reader->read($database, $tenantId, $connectionId)['alerts'], 'code'));
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }
}
