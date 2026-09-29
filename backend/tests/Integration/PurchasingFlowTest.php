<?php

namespace App\Tests\Integration;

use App\Entity\InventoryLevel;
use App\Entity\InventoryCount;
use App\Entity\InventoryTransfer;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseReceipt;
use App\Entity\Supplier;
use App\Entity\SupplierOffer;
use App\Entity\Tenant;
use App\Entity\Warehouse;
use App\Service\InventoryService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class PurchasingFlowTest extends KernelTestCase
{
    public function testPartialReceiptsOnlyAddGoodStockAndClearIncoming(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $inventory = new InventoryService();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $tenant = new Tenant('Purchasing test');
            $warehouse = new Warehouse($tenant, 'default', 'Default warehouse');
            $product = new Product($tenant);
            $supplier = new Supplier($tenant, 'purchasing_test_'.bin2hex(random_bytes(4)), 'Purchasing test');
            $offer = new SupplierOffer($tenant, $supplier, $product);
            $offer->setPurchaseUnit('case', 12);
            $offer->setValidity(new \DateTimeImmutable('tomorrow'), null);
            $order = new PurchaseOrder($tenant, $supplier, $warehouse, 'EUR', 'Rollback-only integration test');
            $order->addItem($product, '5.0000', '12.5000', 'TEST-SKU', 'case', 12);
            $count = new InventoryCount($tenant, $warehouse, 'Rollback-only count');
            $count->addItem($product, '0.0000', null, '1.0000');
            $destination = new Warehouse($tenant, 'test_destination', 'Test destination');
            $transfer = new InventoryTransfer($tenant, $warehouse, $destination, 'Rollback-only transfer');
            $transfer->addItem($product, '1.0000');
            $entityManager->persist($tenant);
            $entityManager->persist($warehouse);
            $entityManager->persist($destination);
            $entityManager->persist($product);
            $entityManager->persist($supplier);
            $entityManager->persist($offer);
            $entityManager->persist($order);
            $entityManager->persist($count);
            $entityManager->persist($transfer);
            $entityManager->flush();
            self::assertFalse($offer->isCurrentlyValid());
            $offer->setValidity(new \DateTimeImmutable('today'), null);
            $entityManager->flush();
            self::assertTrue($offer->isCurrentlyValid());
            $validOffers = $entityManager->createQueryBuilder()
                ->select('offer')
                ->from(SupplierOffer::class, 'offer')
                ->where('offer.id = :id')
                ->andWhere('offer.validFrom <= :today')
                ->setParameter('id', $offer->getId())
                ->setParameter('today', new \DateTimeImmutable('today'))
                ->getQuery()
                ->getResult();
            self::assertCount(1, $validOffers);

            $order->updateDraft($supplier, $warehouse, 'EUR', 'Edited draft');
            $entityManager->flush();
            $order->addItem($product, '5.0000', '13.0000', 'TEST-SKU', 'case', 12);
            $entityManager->flush();
            self::assertCount(1, $order->getItems());

            $countRows = $entityManager->createQueryBuilder()
                ->select('inventoryCount', 'items', 'lineProduct', 'countWarehouse')
                ->from(InventoryCount::class, 'inventoryCount')
                ->leftJoin('inventoryCount.items', 'items')
                ->leftJoin('items.product', 'lineProduct')
                ->join('inventoryCount.warehouse', 'countWarehouse')
                ->where('inventoryCount IN (:counts)')
                ->setParameter('counts', [$count])
                ->getQuery()
                ->getResult();
            self::assertCount(1, $countRows);
            $transferRows = $entityManager->createQueryBuilder()
                ->select('transfer', 'items', 'lineProduct', 'source', 'destination')
                ->from(InventoryTransfer::class, 'transfer')
                ->leftJoin('transfer.items', 'items')
                ->leftJoin('items.product', 'lineProduct')
                ->join('transfer.sourceWarehouse', 'source')
                ->join('transfer.destinationWarehouse', 'destination')
                ->where('transfer IN (:transfers)')
                ->setParameter('transfers', [$transfer])
                ->getQuery()
                ->getResult();
            self::assertCount(1, $transferRows);
            $orderRows = $entityManager->createQueryBuilder()
                ->select('po', 'items', 'lineProduct')
                ->from(PurchaseOrder::class, 'po')
                ->leftJoin('po.items', 'items')
                ->leftJoin('items.product', 'lineProduct')
                ->where('po IN (:orders)')
                ->setParameter('orders', [$order])
                ->getQuery()
                ->getResult();
            self::assertCount(1, $orderRows);

            $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                'tenant' => $tenant,
                'warehouse' => $warehouse,
                'product' => $product,
            ]);
            $beforeOnHand = (float) ($level?->getQuantity() ?? '0');
            $beforeIncoming = (float) ($level?->getIncomingQuantity() ?? '0');
            $item = $order->getItems()->first();
            self::assertNotFalse($item);

            $inventory->lockProduct($tenant, $warehouse, $product, $entityManager);
            self::assertSame('60.0000', $item->toStockQuantity($item->getQuantity()));
            $inventory->changeIncoming($tenant, $warehouse, $product, $item->toStockQuantity($item->getQuantity()), $entityManager);
            $order->markSent();
            $entityManager->flush();
            $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                'tenant' => $tenant,
                'warehouse' => $warehouse,
                'product' => $product,
            ]);
            self::assertEqualsWithDelta($beforeIncoming + 60, (float) $level->getIncomingQuantity(), 0.00001);
            self::assertEqualsWithDelta($beforeOnHand, (float) $level->getQuantity(), 0.00001);

            $inventory->receivePurchase($tenant, $warehouse, $product, $item->toStockQuantity('2.0000'), $item->toStockQuantity('1.0000'), null, $order->getId()->toRfc4122(), null, $entityManager);
            $item->receive('2.0000', '1.0000');
            $order->markReceived();
            $receiptKey = Uuid::v7();
            $receipt = new PurchaseReceipt($order, $receiptKey, $item, '2.0000', '1.0000', null, 'First delivery');
            $entityManager->persist($receipt);
            $entityManager->flush();
            self::assertCount(1, $entityManager->getRepository(PurchaseReceipt::class)->findBy([
                'purchaseOrder' => $order,
                'receiptKey' => $receiptKey,
            ]));
            self::assertSame('partially_received', $order->getStatus());
            self::assertSame('open', $receipt->getDamageResolution());
            $receipt->resolveDamage('returned', 'Supplier collected damaged case');
            $entityManager->flush();
            self::assertSame('returned', $receipt->getDamageResolution());
            $entityManager->refresh($receipt);
            self::assertCount(1, $receipt->getDamageHistory());
            self::assertEqualsWithDelta($beforeIncoming + 24, (float) $level->getIncomingQuantity(), 0.00001);
            self::assertEqualsWithDelta($beforeOnHand + 24, (float) $level->getQuantity(), 0.00001);

            $inventory->receivePurchase($tenant, $warehouse, $product, $item->toStockQuantity('2.0000'), '0.0000', null, $order->getId()->toRfc4122(), null, $entityManager);
            $item->receive('2.0000', '0.0000');
            $order->markReceived();
            $entityManager->flush();
            self::assertSame('received', $order->getStatus());
            self::assertEqualsWithDelta($beforeIncoming, (float) $level->getIncomingQuantity(), 0.00001);
            self::assertEqualsWithDelta($beforeOnHand + 48, (float) $level->getQuantity(), 0.00001);
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
