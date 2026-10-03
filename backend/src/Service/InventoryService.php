<?php

namespace App\Service;

use App\Entity\InventoryLevel;
use App\Entity\InventoryMovement;
use App\Entity\Product;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;

final class InventoryService
{
    public function __construct(private readonly InventorySyncOutboxService $stockSyncOutbox)
    {
    }

    public function lockProduct(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        EntityManagerInterface $entityManager,
    ): void {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Stock changes require a database transaction.');
        }
        if (!$product->getTenant()->getId()->equals($tenant->getId())) {
            throw new \DomainException('The product does not belong to this tenant.');
        }

        $this->lockWarehouse($tenant, $warehouse, $entityManager);

        $key = implode(':', [
            $tenant->getId()->toRfc4122(),
            $warehouse->getId()->toRfc4122(),
            $product->getId()->toRfc4122(),
        ]);
        $entityManager->getConnection()->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [$key],
        );
    }

    /** Shared warehouse lock serializes operational writes with deactivation. */
    public function lockWarehouse(
        Tenant $tenant,
        Warehouse $warehouse,
        EntityManagerInterface $entityManager,
    ): void
    {
        $database = $entityManager->getConnection();
        if (!$database->isTransactionActive()) {
            throw new \LogicException('Warehouse operations require a database transaction.');
        }
        if (!$warehouse->getTenant()->getId()->equals($tenant->getId())) {
            throw new \DomainException('The warehouse does not belong to this tenant.');
        }
        $row = $database->fetchAssociative(
            'SELECT active FROM warehouses WHERE tenant_id = :tenant AND id = :warehouse FOR SHARE',
            ['tenant' => (string) $tenant->getId(), 'warehouse' => (string) $warehouse->getId()],
        );
        if (!$row || !$row['active']) {
            throw new \DomainException('The warehouse is inactive. Reactivate it before starting an inventory operation.');
        }
    }

    /** Called while holding an exclusive warehouse row lock. Never load its full catalogue. */
    public function warehouseDeactivationBlockers(
        Tenant $tenant,
        Warehouse $warehouse,
        EntityManagerInterface $entityManager,
    ): array
    {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Warehouse deactivation checks require a database transaction and exclusive warehouse lock.');
        }
        if (!$warehouse->getTenant()->getId()->equals($tenant->getId())) {
            throw new \DomainException('The warehouse does not belong to this tenant.');
        }
        $queries = [
            'stock' => <<<'SQL'
SELECT 1 FROM inventory_levels
WHERE tenant_id = :tenant AND warehouse_id = :warehouse
  AND (quantity <> 0 OR reserved_quantity <> 0 OR unavailable_quantity <> 0 OR incoming_quantity <> 0)
LIMIT 1
SQL,
            'reservations' => <<<'SQL'
SELECT 1 FROM sales_order_allocations allocation
JOIN sales_order_items item ON item.id = allocation.sales_order_item_id
JOIN sales_orders sales_order ON sales_order.id = item.sales_order_id
WHERE sales_order.tenant_id = :tenant AND allocation.warehouse_id = :warehouse
  AND allocation.status = 'reserved' AND allocation.quantity > 0
LIMIT 1
SQL,
            'purchaseOrders' => <<<'SQL'
SELECT 1 FROM purchase_orders
WHERE tenant_id = :tenant AND warehouse_id = :warehouse
  AND status IN ('draft', 'sent', 'partially_received')
LIMIT 1
SQL,
            'transfers' => <<<'SQL'
SELECT 1 FROM inventory_transfers
WHERE tenant_id = :tenant
  AND (source_warehouse_id = :warehouse OR destination_warehouse_id = :warehouse)
  AND status IN ('draft', 'in_transit')
LIMIT 1
SQL,
            'counts' => <<<'SQL'
SELECT 1 FROM inventory_counts
WHERE tenant_id = :tenant AND warehouse_id = :warehouse AND status = 'draft'
LIMIT 1
SQL,
            'returns' => <<<'SQL'
SELECT 1 FROM sales_returns
WHERE tenant_id = :tenant AND warehouse_id = :warehouse
  AND disposition = 'quarantine' AND quantity > 0
LIMIT 1
SQL,
        ];
        $blockers = [];
        $parameters = ['tenant' => (string) $tenant->getId(), 'warehouse' => (string) $warehouse->getId()];
        foreach ($queries as $reason => $query) {
            if ($entityManager->getConnection()->fetchOne($query, $parameters) !== false) {
                $blockers[] = $reason;
            }
        }

        return $blockers;
    }

    public function defaultWarehouse(
        Tenant $tenant,
        EntityManagerInterface $entityManager,
    ): Warehouse {
        $warehouse = $entityManager->getRepository(Warehouse::class)->findOneBy([
            'tenant' => $tenant,
            'code' => 'default',
        ]);
        if ($warehouse instanceof Warehouse) {
            return $warehouse;
        }

        $warehouse = new Warehouse($tenant, 'default', 'Glavno skladište');
        $entityManager->persist($warehouse);

        return $warehouse;
    }

    public function setStock(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?User $user,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
        if (!$level instanceof InventoryLevel) {
            $level = new InventoryLevel($tenant, $warehouse, $product);
            $entityManager->persist($level);
        }

        $quantity = $this->decimal($quantity);
        $delta = number_format(
            (float) $quantity - (float) $level->getQuantity(),
            4,
            '.',
            '',
        );
        $level->setQuantity($quantity);
        $entityManager->persist(new InventoryMovement(
            $tenant,
            $warehouse,
            $product,
            $user,
            'adjustment',
            $delta,
            $quantity,
            $note,
        ));
        $this->stockSyncOutbox->queue($tenant, $product, $entityManager);

        return $level;
    }

    public function syncImportedStock(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        // Source stock is a one-time bootstrap. Once a level exists, local
        // receipts, transfers, counts and reservations own its quantity.
        $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
        if ($level instanceof InventoryLevel) {
            return $level;
        }

        return $this->setStock(
            $tenant,
            $warehouse,
            $product,
            $this->decimal($quantity),
            null,
            $note,
            $entityManager,
        );
    }

    public function transferOut(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $level = $this->level($tenant, $warehouse, $product, $entityManager);
        $quantity = $this->decimal($quantity);
        if ((float) $level->getAvailableQuantity() < (float) $quantity) {
            throw new \DomainException('Insufficient available stock for transfer.');
        }

        return $this->changeStock(
            $tenant,
            $warehouse,
            $product,
            $level,
            -1 * (float) $quantity,
            $user,
            'transfer_out',
            $reference,
            $note,
            $entityManager,
        );
    }

    public function transferIn(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $level = $this->level($tenant, $warehouse, $product, $entityManager);

        return $this->changeStock(
            $tenant,
            $warehouse,
            $product,
            $level,
            (float) $this->decimal($quantity),
            $user,
            'transfer_in',
            $reference,
            $note,
            $entityManager,
        );
    }

    public function changeIncoming(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $delta,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $level = $this->level($tenant, $warehouse, $product, $entityManager);
        $after = (float) $level->getIncomingQuantity() + (float) $delta;
        if ($after < -0.00001) {
            throw new \DomainException('Incoming stock cannot be negative.');
        }
        $level->setIncomingQuantity(number_format(max(0, $after), 4, '.', ''));

        return $level;
    }

    public function reserve(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $this->lockProduct($tenant, $warehouse, $product, $entityManager);
        $quantity = $this->decimal($quantity);
        $level = $this->level($tenant, $warehouse, $product, $entityManager);
        if ((float) $quantity <= 0 || (float) $level->getAvailableQuantity() + 0.00001 < (float) $quantity) {
            throw new \DomainException('Insufficient available stock for reservation.');
        }

        $level->setReservedQuantity(number_format(
            (float) $level->getReservedQuantity() + (float) $quantity,
            4,
            '.',
            '',
        ));
        $entityManager->persist(new InventoryMovement(
            $tenant,
            $warehouse,
            $product,
            $user,
            'reservation',
            '0.0000',
            $level->getQuantity(),
            $note ? sprintf('%s [%s]', $note, $reference) : $reference,
        ));
        $this->stockSyncOutbox->queue($tenant, $product, $entityManager);

        return $level;
    }

    public function releaseReservation(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $this->lockProduct($tenant, $warehouse, $product, $entityManager);
        $quantity = $this->decimal($quantity);
        $level = $this->level($tenant, $warehouse, $product, $entityManager);
        if ((float) $quantity <= 0 || (float) $level->getReservedQuantity() + 0.00001 < (float) $quantity) {
            throw new \DomainException('Reserved stock is no longer available to release.');
        }

        $level->setReservedQuantity(number_format(
            (float) $level->getReservedQuantity() - (float) $quantity,
            4,
            '.',
            '',
        ));
        $entityManager->persist(new InventoryMovement(
            $tenant,
            $warehouse,
            $product,
            $user,
            'reservation_release',
            '0.0000',
            $level->getQuantity(),
            $note ? sprintf('%s [%s]', $note, $reference) : $reference,
        ));
        $this->stockSyncOutbox->queue($tenant, $product, $entityManager);

        return $level;
    }

    public function shipReservation(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $this->lockProduct($tenant, $warehouse, $product, $entityManager);
        $quantity = $this->decimal($quantity);
        $level = $this->level($tenant, $warehouse, $product, $entityManager);
        if ((float) $quantity <= 0 || (float) $level->getReservedQuantity() + 0.00001 < (float) $quantity) {
            throw new \DomainException('Reserved stock is no longer available to ship.');
        }

        $level->setReservedQuantity(number_format(
            (float) $level->getReservedQuantity() - (float) $quantity,
            4,
            '.',
            '',
        ));

        return $this->changeStock(
            $tenant,
            $warehouse,
            $product,
            $level,
            -(float) $quantity,
            $user,
            'shipment',
            $reference,
            $note,
            $entityManager,
        );
    }

    public function returnFromCustomer(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $this->lockProduct($tenant, $warehouse, $product, $entityManager);
        $quantity = $this->decimal($quantity);
        if ((float) $quantity <= 0) {
            throw new \DomainException('Returned quantity must be positive.');
        }
        $level = $this->level($tenant, $warehouse, $product, $entityManager);

        return $this->changeStock(
            $tenant,
            $warehouse,
            $product,
            $level,
            (float) $quantity,
            $user,
            'customer_return',
            $reference,
            $note,
            $entityManager,
        );
    }

    public function quarantineCustomerReturn(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $this->lockProduct($tenant, $warehouse, $product, $entityManager);
        $quantity = $this->decimal($quantity);
        if ((float) $quantity <= 0) {
            throw new \DomainException('Returned quantity must be positive.');
        }
        $level = $this->level($tenant, $warehouse, $product, $entityManager);
        $this->changeStock(
            $tenant,
            $warehouse,
            $product,
            $level,
            (float) $quantity,
            $user,
            'customer_return_quarantine',
            $reference,
            $note,
            $entityManager,
        );
        $level->setUnavailableQuantity(number_format(
            (float) $level->getUnavailableQuantity() + (float) $quantity,
            4,
            '.',
            '',
        ));

        return $level;
    }

    public function resolveCustomerReturnQuarantine(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        string $disposition,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        if (!in_array($disposition, ['restock', 'write_off'], true)) {
            throw new \DomainException('Invalid customer return disposition.');
        }
        $this->lockProduct($tenant, $warehouse, $product, $entityManager);
        $quantity = $this->decimal($quantity);
        $level = $this->level($tenant, $warehouse, $product, $entityManager);
        if ((float) $quantity <= 0
            || (float) $level->getUnavailableQuantity() + 0.00001 < (float) $quantity
            || (float) $level->getQuantity() + 0.00001 < (float) $quantity) {
            throw new \DomainException('Quarantined return stock is no longer available.');
        }
        $level->setUnavailableQuantity(number_format(
            (float) $level->getUnavailableQuantity() - (float) $quantity,
            4,
            '.',
            '',
        ));
        if ($disposition === 'restock') {
            $entityManager->persist(new InventoryMovement(
                $tenant,
                $warehouse,
                $product,
                $user,
                'customer_return_release',
                '0.0000',
                $level->getQuantity(),
                $note ? sprintf('%s [%s]', $note, $reference) : $reference,
            ));
            $this->stockSyncOutbox->queue($tenant, $product, $entityManager);

            return $level;
        }

        return $this->changeStock(
            $tenant,
            $warehouse,
            $product,
            $level,
            -(float) $quantity,
            $user,
            'customer_return_write_off',
            $reference,
            $note,
            $entityManager,
        );
    }

    public function receivePurchase(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $goodQuantity,
        string $damagedQuantity,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $level = $this->changeIncoming(
            $tenant,
            $warehouse,
            $product,
            number_format(-((float) $goodQuantity + (float) $damagedQuantity), 4, '.', ''),
            $entityManager,
        );
        if ((float) $goodQuantity > 0) {
            $this->changeStock(
                $tenant,
                $warehouse,
                $product,
                $level,
                (float) $goodQuantity,
                $user,
                'purchase_receipt',
                $reference,
                $note,
                $entityManager,
            );
        }
        if ((float) $damagedQuantity > 0) {
            $this->changeStock(
                $tenant,
                $warehouse,
                $product,
                $level,
                (float) $damagedQuantity,
                $user,
                'purchase_quarantine',
                $reference,
                $note,
                $entityManager,
            );
            $level->setUnavailableQuantity(number_format(
                (float) $level->getUnavailableQuantity() + (float) $damagedQuantity,
                4,
                '.',
                '',
            ));
        }

        return $level;
    }

    public function disposeQuarantine(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        string $quantity,
        string $disposition,
        ?User $user,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        if (!in_array($disposition, ['released', 'returned', 'scrapped'], true)) {
            throw new \DomainException('Invalid quarantine disposition.');
        }
        $this->lockProduct($tenant, $warehouse, $product, $entityManager);
        $quantity = $this->decimal($quantity);
        $level = $this->level($tenant, $warehouse, $product, $entityManager);
        $customerQuarantine = (float) $entityManager->getConnection()->fetchOne(
            <<<'SQL'
SELECT COALESCE(SUM(return_record.quantity), 0)
FROM sales_returns return_record
JOIN sales_order_items item ON item.id = return_record.sales_order_item_id
WHERE return_record.tenant_id = :tenantId
  AND return_record.warehouse_id = :warehouseId
  AND item.product_id = :productId
  AND return_record.disposition = 'quarantine'
SQL,
            [
                'tenantId' => $tenant->getId()->toRfc4122(),
                'warehouseId' => $warehouse->getId()->toRfc4122(),
                'productId' => $product->getId()->toRfc4122(),
            ],
        );
        if ((float) $quantity <= 0
            || (float) $level->getUnavailableQuantity() + 0.00001 < (float) $quantity
            || (float) $level->getQuantity() + 0.00001 < (float) $quantity
            || (float) $level->getUnavailableQuantity() - $customerQuarantine + 0.00001 < (float) $quantity
        ) {
            throw new \DomainException('Supplier quarantine stock is no longer available for this disposition.');
        }
        $level->setUnavailableQuantity(number_format(
            (float) $level->getUnavailableQuantity() - (float) $quantity,
            4,
            '.',
            '',
        ));
        if ($disposition === 'released') {
            $entityManager->persist(new InventoryMovement(
                $tenant,
                $warehouse,
                $product,
                $user,
                'quarantine_release',
                '0.0000',
                $level->getQuantity(),
                $note ? sprintf('%s [%s]', $note, $reference) : $reference,
            ));
            $this->stockSyncOutbox->queue($tenant, $product, $entityManager);

            return $level;
        }

        return $this->changeStock(
            $tenant,
            $warehouse,
            $product,
            $level,
            -(float) $quantity,
            $user,
            $disposition === 'returned' ? 'supplier_return' : 'quarantine_scrap',
            $reference,
            $note,
            $entityManager,
        );
    }

    private function level(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
        if ($level instanceof InventoryLevel) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->refresh($level);
            }

            return $level;
        }

        $level = new InventoryLevel($tenant, $warehouse, $product);
        $entityManager->persist($level);

        return $level;
    }

    private function changeStock(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        InventoryLevel $level,
        float $delta,
        ?User $user,
        string $type,
        string $reference,
        ?string $note,
        EntityManagerInterface $entityManager,
    ): InventoryLevel {
        $quantityAfter = number_format((float) $level->getQuantity() + $delta, 4, '.', '');
        $level->setQuantity($quantityAfter);
        $entityManager->persist(new InventoryMovement(
            $tenant,
            $warehouse,
            $product,
            $user,
            $type,
            number_format($delta, 4, '.', ''),
            $quantityAfter,
            $note ? sprintf('%s [%s]', $note, $reference) : $reference,
        ));
        $this->stockSyncOutbox->queue($tenant, $product, $entityManager);

        return $level;
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
