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
    public function lockProduct(
        Tenant $tenant,
        Warehouse $warehouse,
        Product $product,
        EntityManagerInterface $entityManager,
    ): void {
        if (!$entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('Stock changes require a database transaction.');
        }

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
        $quantity = $this->decimal($quantity);
        $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
        if (
            $level instanceof InventoryLevel
            && $level->getQuantity() === $quantity
        ) {
            return $level;
        }

        return $this->setStock(
            $tenant,
            $warehouse,
            $product,
            $quantity,
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

        return $level;
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

        return $level;
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
