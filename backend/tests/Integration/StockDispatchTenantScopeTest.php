<?php

namespace App\Tests\Integration;

use App\Command\DispatchStockSyncOutboxCommand;
use App\Entity\InventorySyncOutbox;
use App\Entity\Product;
use App\Entity\Tenant;
use App\Service\StockSyncOutboxDispatcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class StockDispatchTenantScopeTest extends KernelTestCase
{
    public function testScopedDispatchNeverConsumesAnotherTenantsEvents(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Scoped stock dispatch');
            $other = new Tenant('Foreign stock dispatch');
            $product = new Product($tenant);
            $foreignProduct = new Product($other);
            $event = new InventorySyncOutbox($tenant, $product);
            $foreignEvent = new InventorySyncOutbox($other, $foreignProduct);
            foreach ([$tenant, $other, $product, $foreignProduct, $event, $foreignEvent] as $entity) {
                $manager->persist($entity);
            }
            $manager->flush();
            $tester = new CommandTester(new DispatchStockSyncOutboxCommand(
                $manager,
                self::getContainer()->get(StockSyncOutboxDispatcher::class),
            ));
            self::assertSame(2, $tester->execute(['--tenant' => 'not-a-uuid']));
            self::assertSame(2, $tester->execute(['--tenant' => (string) Uuid::v7()]));
            self::assertSame(0, $tester->execute(['--tenant' => (string) $tenant->getId()]));
            $manager->refresh($event);
            $manager->refresh($foreignEvent);
            self::assertSame('dispatched', $event->getStatus());
            self::assertSame('pending', $foreignEvent->getStatus());
            self::assertStringContainsString('Dispatched 1 stock event(s)', $tester->getDisplay());
        } finally {
            $database->rollBack();
        }
    }
}
