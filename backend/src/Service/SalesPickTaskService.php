<?php

namespace App\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesPickTask;
use App\Entity\User;
use App\Entity\Warehouse;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class SalesPickTaskService
{
    public function __construct(private readonly SalesPickListService $pickLists)
    {
    }

    public function latest(SalesOrder $order, EntityManagerInterface $entityManager): ?SalesPickTask
    {
        return $entityManager->getRepository(SalesPickTask::class)->findOneBy(
            ['salesOrder' => $order],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
        );
    }

    public function isStale(SalesPickTask $task): bool
    {
        return $task->getStatus() === 'stale'
            || $this->reservationSignature($task->getLines())
                !== $this->reservationSignature($this->pickLists->lines($task->getSalesOrder()));
    }

    public function start(
        SalesOrder $order,
        User $user,
        EntityManagerInterface $entityManager,
    ): SalesPickTask {
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $this->lockOrder($order, $entityManager);
            $lines = $this->pickLists->lines($order);
            if ($lines === []) {
                throw new \DomainException('This order has no reserved items to pick.');
            }

            $warehouseIds = array_unique(array_column($lines, 'warehouseId'));
            if (count($warehouseIds) !== 1) {
                throw new \DomainException('Create a separate pick task for each warehouse.');
            }

            $existing = $this->latest($order, $entityManager);
            if ($existing instanceof SalesPickTask && !$this->isStale($existing)) {
                $database->commit();

                return $existing;
            }

            if ($existing instanceof SalesPickTask) {
                if (in_array($existing->getStatus(), ['picked', 'short'], true)) {
                    throw new \DomainException('A completed pick exists for this order. Reconcile its picked goods before creating another task.');
                }
                if (in_array($existing->getStatus(), ['open', 'in_progress'], true)) {
                    if ($this->hasPositiveQuantity($existing->getPickedQuantities())) {
                        throw new \DomainException('The order changed after picking began. Resolve the picked items before starting a new task.');
                    }
                    $existing->markStale();
                }
            }

            $warehouse = $entityManager->getRepository(Warehouse::class)->find(
                Uuid::fromString($warehouseIds[0]),
            );
            if (!$warehouse instanceof Warehouse
                || !$warehouse->getTenant()->getId()->equals($order->getTenant()->getId())) {
                throw new \LogicException('The reserved warehouse no longer belongs to this tenant.');
            }

            $task = new SalesPickTask($order, $warehouse, $user, $lines);
            $entityManager->persist($task);
            $entityManager->flush();
            $database->commit();

            return $task;
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    /** @param array<string, mixed> $quantities */
    public function record(
        SalesOrder $order,
        Uuid $taskId,
        array $quantities,
        int $expectedVersion,
        bool $complete,
        User $user,
        EntityManagerInterface $entityManager,
    ): SalesPickTask {
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $this->lockOrder($order, $entityManager);
            $task = $this->latest($order, $entityManager);
            if (!$task instanceof SalesPickTask || !$task->getId()->equals($taskId)) {
                throw new \DomainException('This is not the current pick task for the order.');
            }
            $entityManager->refresh($task);
            if ($this->isStale($task)) {
                throw new \DomainException('The order reservation changed. Refresh the pick list before continuing.');
            }
            if ($task->getVersion() !== $expectedVersion) {
                throw new \DomainException('The pick task was changed by someone else. Refresh before saving.');
            }

            $normalized = $this->normalizeQuantities($task, $quantities);
            if ($complete && !$this->hasPositiveQuantity($normalized)) {
                throw new \InvalidArgumentException('At least one item must be picked before completion.');
            }

            $task->record($normalized, $complete, $user);
            $entityManager->flush();
            $database->commit();

            return $task;
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    /** @param array<string, mixed> $quantities @return array<string, string> */
    private function normalizeQuantities(SalesPickTask $task, array $quantities): array
    {
        if (array_is_list($quantities)) {
            throw new \InvalidArgumentException('Provide picked quantities by order-item ID.');
        }

        $normalized = [];
        foreach ($task->getLines() as $line) {
            $itemId = $line['itemId'];
            $raw = $quantities[$itemId] ?? null;
            if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
                throw new \InvalidArgumentException('Every pick-list line needs a quantity.');
            }
            $value = trim((string) $raw);
            if (!preg_match('/^\d{1,15}(?:\.\d{1,4})?$/D', $value)
                || (float) $value > (float) $line['quantity'] + 0.00001) {
                throw new \InvalidArgumentException('A picked quantity is invalid or exceeds its reservation.');
            }
            $normalized[$itemId] = number_format((float) $value, 4, '.', '');
        }

        if (count($normalized) !== count($quantities)) {
            throw new \InvalidArgumentException('The pick task contains an unknown item.');
        }

        return $normalized;
    }

    private function lockOrder(SalesOrder $order, EntityManagerInterface $entityManager): void
    {
        $entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
        $entityManager->refresh($order);
        foreach ($order->getItems() as $item) {
            $entityManager->refresh($item);
            foreach ($item->getAllocations() as $allocation) {
                $entityManager->refresh($allocation);
            }
        }
    }

    /** @param array<string, string> $quantities */
    private function hasPositiveQuantity(array $quantities): bool
    {
        foreach ($quantities as $quantity) {
            if ((float) $quantity > 0.00001) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, string>> $lines @return list<string> */
    private function reservationSignature(array $lines): array
    {
        $signature = array_map(
            static fn (array $line): string => implode('|', [
                $line['itemId'],
                $line['warehouseId'],
                $line['sku'],
                $line['quantity'],
            ]),
            $lines,
        );
        sort($signature);

        return $signature;
    }
}
