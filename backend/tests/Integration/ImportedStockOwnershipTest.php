<?php

namespace App\Tests\Integration;

use App\Entity\Product;
use App\Entity\Tenant;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImportedStockOwnershipTest extends KernelTestCase
{
    public function testReimportCannotOverwriteAnExistingWarehouseLevel(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Stock ownership test');
            $product = new Product($tenant);
            $product->updateIdentity('STOCK-OWNERSHIP-'.bin2hex(random_bytes(4)), null);
            $entityManager->persist($tenant);
            $entityManager->persist($product);
            $entityManager->flush();

            $inventory = new InventoryService(new InventorySyncOutboxService());
            $warehouse = $inventory->defaultWarehouse($tenant, $entityManager);
            $inventory->syncImportedStock($tenant, $warehouse, $product, '10.0000', null, $entityManager);
            $entityManager->flush();

            $inventory->setStock($tenant, $warehouse, $product, '7.0000', null, 'Physical count', $entityManager);
            $entityManager->flush();
            $inventory->syncImportedStock($tenant, $warehouse, $product, '15.0000', null, $entityManager);
            $entityManager->flush();

            $level = $entityManager->getRepository(\App\Entity\InventoryLevel::class)->findOneBy([
                'warehouse' => $warehouse,
                'product' => $product,
            ]);
            self::assertSame('7.0000', $level?->getQuantity());
        } finally {
            $database->rollBack();
        }
    }
}
