<?php

namespace App\Service;

use App\Entity\InventoryLevel;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderAllocation;
use App\Entity\SalesOrderItem;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;

final class SalesOrderAllocationService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function reserve(
        SalesOrder $order,
        ?User $user,
        EntityManagerInterface $entityManager,
    ): Warehouse {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Sales-order allocation requires a database transaction.');
        }
        if ($order->getStatus() !== 'new' || $order->getItems()->isEmpty()) {
            throw new \DomainException('Only a new order with lines can be allocated.');
        }
        if ($order->getUnresolvedSkus() !== []) {
            throw new \DomainException('An order with unmatched products cannot be allocated.');
        }
        if ($order->getOutstandingQuantity() === '0.0000') {
            throw new \DomainException('This order has no stock-managed product lines to allocate.');
        }

        $warehouse = $this->warehouseForFullOrder($order, $entityManager);
        if (!$warehouse instanceof Warehouse) {
            throw new \DomainException('No fulfilment warehouse can supply the complete order.');
        }

        $this->reserveItems($order, $warehouse, $user, $entityManager, false);
        $order->markReserved();

        return $warehouse;
    }

    public function cancel(
        SalesOrder $order,
        ?User $user,
        EntityManagerInterface $entityManager,
    ): void {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Sales-order cancellation requires a database transaction.');
        }
        if (!in_array($order->getStatus(), ['new', 'reserved', 'partially_fulfilled'], true)) {
            throw new \DomainException('This order cannot be cancelled.');
        }

        $this->releaseReservations($order, $user, $entityManager, 'Channel order cancellation');
        $order->markCancelled();
    }

    public function ship(
        SalesOrderAllocation $allocation,
        ?User $user,
        EntityManagerInterface $entityManager,
        ?string $quantity = null,
        string $note = 'Channel order shipment',
    ): void {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Sales-order shipment requires a database transaction.');
        }
        if ($allocation->getStatus() !== 'reserved') {
            throw new \DomainException('Only a reserved allocation can be shipped.');
        }

        $quantity ??= $allocation->getQuantity();
        if ((float) $quantity <= 0 || (float) $quantity > (float) $allocation->getQuantity() + 0.00001) {
            throw new \DomainException('The shipment exceeds the reserved allocation.');
        }

        $item = $allocation->getSalesOrderItem();
        $order = $item->getSalesOrder();
        $product = $item->getProduct();
        if (!$product instanceof Product) {
            throw new \LogicException('A reserved order item has no product.');
        }
        $this->inventory->shipReservation(
            $order->getTenant(),
            $allocation->getWarehouse(),
            $product,
            $quantity,
            $user,
            'sales-order:'.$order->getExternalNumber(),
            $note,
            $entityManager,
        );
        $item->fulfill($quantity);
        $shippedSplit = $allocation->splitForShipment($quantity);
        if ($shippedSplit instanceof SalesOrderAllocation) {
            $item->addSplitAllocation($shippedSplit);
        }
        $order->refreshInventoryStatus();
        $entityManager->flush();
    }

    public function releaseOpenReservations(
        SalesOrder $order,
        ?User $user,
        EntityManagerInterface $entityManager,
    ): void {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Order reservation changes require a database transaction.');
        }

        $this->releaseReservations($order, $user, $entityManager, 'Channel order line edit');
        $order->refreshInventoryStatus();
    }

    public function shipLineQuantity(
        SalesOrderItem $item,
        string $quantity,
        ?User $user,
        EntityManagerInterface $entityManager,
        string $note = 'Channel order shipment',
    ): void {
        if ((float) $quantity <= 0 || (float) $item->getReservedQuantity() + 0.00001 < (float) $quantity) {
            throw new \DomainException('The order line has insufficient reserved stock to ship.');
        }

        $remaining = (float) $quantity;
        foreach ($item->getAllocations()->toArray() as $allocation) {
            if ($allocation->getStatus() !== 'reserved' || $remaining <= 0.00001) {
                continue;
            }
            $part = min($remaining, (float) $allocation->getQuantity());
            $this->ship($allocation, $user, $entityManager, number_format($part, 4, '.', ''), $note);
            $remaining -= $part;
        }
        if ($remaining > 0.00001) {
            throw new \LogicException('The order line reservation does not match its allocations.');
        }
    }

    public function reserveOpen(
        SalesOrder $order,
        ?User $user,
        EntityManagerInterface $entityManager,
    ): bool {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Order reservation requires a database transaction.');
        }
        if (in_array($order->getStatus(), ['historical', 'cancelled'], true)) {
            return false;
        }
        if ((float) $order->getOutstandingQuantity() <= 0.00001) {
            $order->refreshInventoryStatus();

            return true;
        }
        if ($order->getUnresolvedSkus() !== []) {
            return false;
        }

        $warehouse = $this->warehouseForOpenQuantity($order, $entityManager);
        if (!$warehouse instanceof Warehouse) {
            return false;
        }

        $this->reserveItems($order, $warehouse, $user, $entityManager, true);
        $order->refreshInventoryStatus();

        return true;
    }

    private function reserveItems(
        SalesOrder $order,
        Warehouse $warehouse,
        ?User $user,
        EntityManagerInterface $entityManager,
        bool $openOnly,
    ): void {
        $groups = [];
        $lines = [];
        foreach ($order->getItems() as $item) {
            if (!$item->isInventoryLine()) {
                continue;
            }
            $quantity = $openOnly ? (float) $item->getOpenQuantity() : (float) $item->getQuantity();
            if ($quantity <= 0.00001) {
                continue;
            }
            $product = $item->getProduct();
            if (!$product instanceof Product) {
                throw new \LogicException('An allocatable order line has no product.');
            }
            $id = $product->getId()->toRfc4122();
            $groups[$id]['product'] = $product;
            $groups[$id]['quantity'] = ($groups[$id]['quantity'] ?? 0.0) + $quantity;
            $lines[] = [$item, number_format($quantity, 4, '.', '')];
        }
        ksort($groups);
        foreach ($groups as $group) {
            $this->inventory->reserve(
                $order->getTenant(),
                $warehouse,
                $group['product'],
                number_format($group['quantity'], 4, '.', ''),
                $user,
                'sales-order:'.$order->getExternalNumber(),
                'Channel order reservation',
                $entityManager,
            );
        }
        foreach ($lines as [$item, $quantity]) {
            $item->addAllocation($warehouse, $quantity);
        }
    }

    private function releaseReservations(
        SalesOrder $order,
        ?User $user,
        EntityManagerInterface $entityManager,
        string $note,
    ): void {
        $groups = [];
        $allocations = [];
        foreach ($order->getItems() as $item) {
            foreach ($item->getAllocations() as $allocation) {
                if ($allocation->getStatus() !== 'reserved') {
                    continue;
                }
                $product = $item->getProduct();
                if (!$product instanceof Product) {
                    throw new \LogicException('A reserved order item has no product.');
                }
                $warehouse = $allocation->getWarehouse();
                $key = $warehouse->getId()->toRfc4122().':'.$product->getId()->toRfc4122();
                $groups[$key]['warehouse'] = $warehouse;
                $groups[$key]['product'] = $product;
                $groups[$key]['quantity'] = ($groups[$key]['quantity'] ?? 0.0) + (float) $allocation->getQuantity();
                $allocations[] = [$item, $allocation];
            }
        }
        ksort($groups);
        foreach ($groups as $group) {
            $this->inventory->releaseReservation(
                $order->getTenant(),
                $group['warehouse'],
                $group['product'],
                number_format($group['quantity'], 4, '.', ''),
                $user,
                'sales-order:'.$order->getExternalNumber(),
                $note,
                $entityManager,
            );
        }
        foreach ($allocations as [$item, $allocation]) {
            $item->release($allocation->getQuantity());
            $allocation->release();
        }
        if ($groups !== []) {
            $entityManager->flush();
        }
    }

    private function warehouseForOpenQuantity(
        SalesOrder $order,
        EntityManagerInterface $entityManager,
    ): ?Warehouse {
        $anchor = null;
        foreach ($order->getItems() as $item) {
            foreach ($item->getAllocations() as $allocation) {
                if (!in_array($allocation->getStatus(), ['reserved', 'shipped'], true)) {
                    continue;
                }
                $warehouse = $allocation->getWarehouse();
                if ($anchor instanceof Warehouse && $anchor->getId() != $warehouse->getId()) {
                    throw new \DomainException('The order has allocations in different warehouses.');
                }
                $anchor = $warehouse;
            }
        }

        $warehouses = $anchor instanceof Warehouse
            ? [$anchor]
            : $entityManager->getRepository(Warehouse::class)->findBy(
                [
                    'tenant' => $order->getTenant(),
                    'active' => true,
                    'fulfillmentEnabled' => true,
                ],
                ['priority' => 'ASC', 'createdAt' => 'ASC'],
            );
        foreach ($warehouses as $warehouse) {
            if (!$warehouse->isActive() || !$warehouse->isFulfillmentEnabled()) {
                continue;
            }
            $required = [];
            foreach ($order->getItems() as $item) {
                if (!$item->isInventoryLine() || (float) $item->getOpenQuantity() <= 0.00001) {
                    continue;
                }
                $product = $item->getProduct();
                if (!$product instanceof Product) {
                    return null;
                }
                $id = $product->getId()->toRfc4122();
                $required[$id]['product'] = $product;
                $required[$id]['quantity'] = ($required[$id]['quantity'] ?? 0.0) + (float) $item->getOpenQuantity();
            }
            ksort($required);
            foreach ($required as $entry) {
                $this->inventory->lockProduct($order->getTenant(), $warehouse, $entry['product'], $entityManager);
            }
            $canSupply = true;
            foreach ($required as $entry) {
                $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                    'tenant' => $order->getTenant(),
                    'warehouse' => $warehouse,
                    'product' => $entry['product'],
                ]);
                if (!$level instanceof InventoryLevel || (float) $level->getAvailableQuantity() + 0.00001 < $entry['quantity']) {
                    $canSupply = false;
                    break;
                }
            }
            if ($canSupply) {
                return $warehouse;
            }
        }

        return null;
    }

    private function warehouseForFullOrder(
        SalesOrder $order,
        EntityManagerInterface $entityManager,
    ): ?Warehouse {
        $warehouses = $entityManager->getRepository(Warehouse::class)->findBy(
            [
                'tenant' => $order->getTenant(),
                'active' => true,
                'fulfillmentEnabled' => true,
            ],
            ['priority' => 'ASC', 'createdAt' => 'ASC'],
        );
        foreach ($warehouses as $warehouse) {
            $products = [];
            foreach ($order->getItems() as $item) {
                $product = $item->getProduct();
                if ($item->isInventoryLine() && $product instanceof Product) {
                    $products[$product->getId()->toRfc4122()] = $product;
                }
            }
            ksort($products);
            foreach ($products as $product) {
                $this->inventory->lockProduct(
                    $order->getTenant(),
                    $warehouse,
                    $product,
                    $entityManager,
                );
            }
            if ($this->canSupplyFullOrder($warehouse, $order, $entityManager)) {
                return $warehouse;
            }
        }

        return null;
    }

    private function canSupplyFullOrder(
        Warehouse $warehouse,
        SalesOrder $order,
        EntityManagerInterface $entityManager,
    ): bool {
        $requiredByProduct = [];
        foreach ($order->getItems() as $item) {
            if (!$item->isInventoryLine()) {
                continue;
            }
            $product = $item->getProduct();
            if (!$product instanceof Product) {
                return false;
            }
            $productId = $product->getId()->toRfc4122();
            $requiredByProduct[$productId] ??= [
                'product' => $product,
                'quantity' => 0.0,
            ];
            $requiredByProduct[$productId]['quantity'] += (float) $item->getQuantity();
        }
        foreach ($requiredByProduct as $required) {
            $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                'tenant' => $order->getTenant(),
                'warehouse' => $warehouse,
                'product' => $required['product'],
            ]);
            if (!$level instanceof InventoryLevel || (float) $level->getAvailableQuantity() + 0.00001 < $required['quantity']) {
                return false;
            }
        }

        return true;
    }
}
