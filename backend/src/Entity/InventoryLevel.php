<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'inventory_levels')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_level', columns: ['tenant_id', 'warehouse_id', 'product_id'])]
class InventoryLevel
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Warehouse $warehouse;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantity = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $reservedQuantity = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $unavailableQuantity = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $incomingQuantity = '0.0000';

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $reorderThreshold = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, Warehouse $warehouse, Product $product)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->warehouse = $warehouse;
        $this->product = $product;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }
    public function getProduct(): Product
    {
        return $this->product;
    }
    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
    public function getReservedQuantity(): string
    {
        return $this->reservedQuantity;
    }

    public function getUnavailableQuantity(): string
    {
        return $this->unavailableQuantity;
    }

    public function getIncomingQuantity(): string
    {
        return $this->incomingQuantity;
    }

    public function getAvailableQuantity(): string
    {
        return number_format(
            (float) $this->quantity
            - (float) $this->reservedQuantity
            - (float) $this->unavailableQuantity,
            4,
            '.',
            '',
        );
    }
    public function setQuantity(string $quantity): void
    {
        $this->quantity = $quantity;
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function setReservedQuantity(string $quantity): void
    {
        $this->reservedQuantity = $quantity;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setUnavailableQuantity(string $quantity): void
    {
        $this->unavailableQuantity = $quantity;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setIncomingQuantity(string $quantity): void
    {
        $this->incomingQuantity = $quantity;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
