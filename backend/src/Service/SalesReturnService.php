<?php

namespace App\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesReturn;
use App\Entity\User;
use App\Entity\Warehouse;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class SalesReturnService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /** @return list<array<string, string>> */
    public function options(SalesOrder $order, EntityManagerInterface $entityManager): array
    {
        $returned = [];
        foreach ($this->records($order, $entityManager) as $record) {
            $key = $record->getSalesOrderItem()->getId()->toRfc4122().':'.$record->getWarehouse()->getId()->toRfc4122();
            $returned[$key] = ($returned[$key] ?? 0.0) + (float) $record->getQuantity();
        }

        $options = [];
        foreach ($order->getItems() as $item) {
            if (!$item->isInventoryLine() || !$item->getProduct()) {
                continue;
            }
            $warehouses = [];
            foreach ($item->getAllocations() as $allocation) {
                if ($allocation->getStatus() !== 'shipped') {
                    continue;
                }
                $warehouse = $allocation->getWarehouse();
                $id = $warehouse->getId()->toRfc4122();
                $warehouses[$id]['warehouse'] = $warehouse;
                $warehouses[$id]['shipped'] = ($warehouses[$id]['shipped'] ?? 0.0) + (float) $allocation->getQuantity();
            }
            foreach ($warehouses as $id => $entry) {
                $key = $item->getId()->toRfc4122().':'.$id;
                $remaining = max(0, $entry['shipped'] - ($returned[$key] ?? 0.0));
                $options[] = [
                    'itemId' => $item->getId()->toRfc4122(),
                    'warehouseId' => $id,
                    'warehouse' => $entry['warehouse']->getName(),
                    'sku' => $item->getSku() ?? '—',
                    'name' => $item->getName(),
                    'shipped' => $this->decimal($entry['shipped']),
                    'returned' => $this->decimal($returned[$key] ?? 0.0),
                    'remaining' => $this->decimal($remaining),
                ];
            }
        }

        return $options;
    }

    /** @return list<SalesReturn> */
    public function records(SalesOrder $order, EntityManagerInterface $entityManager): array
    {
        return $entityManager->getRepository(SalesReturn::class)->findBy(
            ['salesOrder' => $order],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
        );
    }

    public function receive(
        SalesOrder $order,
        Uuid $requestId,
        Uuid $itemId,
        Uuid $warehouseId,
        string $quantity,
        string $reason,
        string $condition,
        string $disposition,
        ?string $note,
        User $user,
        EntityManagerInterface $entityManager,
    ): SalesReturn {
        $this->validate($quantity, $reason, $condition, $disposition, $note);
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
            $existing = $entityManager->getRepository(SalesReturn::class)->findOneBy([
                'tenant' => $order->getTenant(),
                'requestId' => $requestId,
            ]);
            if ($existing instanceof SalesReturn) {
                if ($existing->getSalesOrder()->getId()->equals($order->getId())
                    && $existing->getSalesOrderItem()->getId()->equals($itemId)
                    && $existing->getWarehouse()->getId()->equals($warehouseId)
                    && $existing->getQuantity() === $this->decimal((float) $quantity)
                    && $existing->getReason() === $reason
                    && $existing->getReceivedCondition() === $condition
                    && $existing->getInitialDisposition() === $disposition
                    && $existing->getNote() === $note) {
                    $database->commit();

                    return $existing;
                }
                throw new \DomainException('This return request ID was already used for another return.');
            }

            $option = null;
            foreach ($this->options($order, $entityManager) as $candidate) {
                if ($candidate['itemId'] === $itemId->toRfc4122()
                    && $candidate['warehouseId'] === $warehouseId->toRfc4122()) {
                    $option = $candidate;
                    break;
                }
            }
            if ($option === null || (float) $option['remaining'] + 0.00001 < (float) $quantity) {
                throw new \DomainException('The returned quantity exceeds the shipped, unreturned quantity.');
            }

            $item = $entityManager->getRepository(SalesOrderItem::class)->find($itemId);
            $warehouse = $entityManager->getRepository(Warehouse::class)->find($warehouseId);
            if (!$item instanceof SalesOrderItem || !$warehouse instanceof Warehouse) {
                throw new \DomainException('The shipped item or warehouse no longer exists.');
            }
            $record = new SalesReturn(
                $item,
                $warehouse,
                $requestId,
                $this->decimal((float) $quantity),
                $reason,
                $condition,
                $disposition,
                $note,
                $user,
            );
            $entityManager->persist($record);
            if ($disposition === 'restock') {
                $this->restock($record, $user, $entityManager);
            } elseif ($disposition === 'quarantine') {
                $this->quarantine($record, $user, $entityManager);
            }
            $entityManager->flush();
            $database->commit();

            return $record;
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    public function resolve(
        SalesOrder $order,
        Uuid $returnId,
        string $disposition,
        string $condition,
        User $user,
        EntityManagerInterface $entityManager,
    ): SalesReturn {
        if (!in_array($disposition, ['restock', 'write_off'], true)
            || !in_array($condition, ['sealed', 'opened', 'damaged', 'unknown'], true)) {
            throw new \InvalidArgumentException('Choose a valid inspected condition and disposition.');
        }
        $this->assertSellable($condition, $disposition);
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
            $record = $entityManager->getRepository(SalesReturn::class)->findOneBy([
                'id' => $returnId,
                'salesOrder' => $order,
                'tenant' => $order->getTenant(),
            ]);
            if (!$record instanceof SalesReturn) {
                throw new \DomainException('Return not found for this order.');
            }
            $entityManager->lock($record, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($record);
            if ($record->getDisposition() === $disposition
                && $record->getCondition() === $condition) {
                $database->commit();

                return $record;
            }
            $record->resolve($disposition, $condition, $user);
            $product = $record->getSalesOrderItem()->getProduct();
            if (!$product) {
                throw new \DomainException('The returned product is no longer linked to this order line.');
            }
            $this->inventory->resolveCustomerReturnQuarantine(
                $order->getTenant(),
                $record->getWarehouse(),
                $product,
                $record->getQuantity(),
                $disposition,
                $user,
                'sales-return:'.$record->getId()->toRfc4122(),
                'Inspected customer return from order '.$order->getExternalNumber(),
                $entityManager,
            );
            $entityManager->flush();
            $database->commit();

            return $record;
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    private function restock(SalesReturn $record, User $user, EntityManagerInterface $entityManager): void
    {
        $product = $record->getSalesOrderItem()->getProduct();
        if (!$product) {
            throw new \DomainException('The returned product is no longer linked to this order line.');
        }
        $this->inventory->returnFromCustomer(
            $record->getSalesOrder()->getTenant(),
            $record->getWarehouse(),
            $product,
            $record->getQuantity(),
            $user,
            'sales-return:'.$record->getId()->toRfc4122(),
            'Inspected customer return from order '.$record->getSalesOrder()->getExternalNumber(),
            $entityManager,
        );
    }

    private function quarantine(SalesReturn $record, User $user, EntityManagerInterface $entityManager): void
    {
        $product = $record->getSalesOrderItem()->getProduct();
        if (!$product) {
            throw new \DomainException('The returned product is no longer linked to this order line.');
        }
        $this->inventory->quarantineCustomerReturn(
            $record->getSalesOrder()->getTenant(),
            $record->getWarehouse(),
            $product,
            $record->getQuantity(),
            $user,
            'sales-return:'.$record->getId()->toRfc4122(),
            'Customer return awaiting inspection from order '.$record->getSalesOrder()->getExternalNumber(),
            $entityManager,
        );
    }

    private function validate(
        string $quantity,
        string $reason,
        string $condition,
        string $disposition,
        ?string $note,
    ): void {
        if (!preg_match('/^\d{1,15}(?:\.\d{1,4})?$/D', $quantity) || (float) $quantity <= 0) {
            throw new \InvalidArgumentException('Provide a positive return quantity with at most four decimal places.');
        }
        if (!in_array($reason, ['customer_return', 'damaged', 'wrong_item', 'warranty', 'other'], true)
            || !in_array($condition, ['sealed', 'opened', 'damaged', 'unknown'], true)
            || !in_array($disposition, ['quarantine', 'restock', 'write_off'], true)
            || ($note !== null && mb_strlen($note) > 2000)) {
            throw new \InvalidArgumentException('Choose a valid return reason, condition, and disposition.');
        }
        $this->assertSellable($condition, $disposition);
    }

    private function assertSellable(string $condition, string $disposition): void
    {
        if ($disposition === 'restock' && !in_array($condition, ['sealed', 'opened'], true)) {
            throw new \DomainException('Damaged or uninspected goods cannot be restocked.');
        }
    }

    private function decimal(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
