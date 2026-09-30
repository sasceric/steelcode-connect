<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sales_order_allocations')]
#[ORM\Index(name: 'idx_sales_order_allocation_warehouse_status', columns: ['warehouse_id', 'status'])]
class SalesOrderAllocation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'allocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SalesOrderItem $salesOrderItem;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Warehouse $warehouse;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantity;

    #[ORM\Column(length: 24)]
    private string $status = 'reserved';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(SalesOrderItem $salesOrderItem, Warehouse $warehouse, string $quantity)
    {
        $this->id = Uuid::v7();
        $this->salesOrderItem = $salesOrderItem;
        $this->warehouse = $warehouse;
        $this->quantity = $quantity;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getSalesOrderItem(): SalesOrderItem
    {
        return $this->salesOrderItem;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function release(): void
    {
        if ($this->status !== 'reserved') {
            throw new \DomainException('Only reserved allocations can be released.');
        }
        $this->status = 'released';
    }

    public function ship(): void
    {
        if ($this->status !== 'reserved') {
            throw new \DomainException('Only reserved allocations can be shipped.');
        }
        $this->status = 'shipped';
    }

    public function splitForShipment(string $quantity): ?self
    {
        if ($this->status !== 'reserved' || (float) $quantity <= 0 || (float) $quantity > (float) $this->quantity + 0.00001) {
            throw new \DomainException('The shipment exceeds the reserved allocation.');
        }
        if (abs((float) $this->quantity - (float) $quantity) < 0.00001) {
            $this->ship();

            return null;
        }

        $this->quantity = number_format((float) $this->quantity - (float) $quantity, 4, '.', '');
        $shipped = new self($this->salesOrderItem, $this->warehouse, $quantity);
        $shipped->ship();

        return $shipped;
    }
}
