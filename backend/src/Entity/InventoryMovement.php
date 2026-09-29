<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'inventory_movements')]
#[ORM\Index(name: 'idx_inventory_movements_product_created', columns: ['product_id', 'created_at'])]
class InventoryMovement
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
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;
    #[ORM\Column(length: 32)]
    private string $type;
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantityDelta;
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantityAfter;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, Warehouse $warehouse, Product $product, ?User $user, string $type, string $quantityDelta, string $quantityAfter, ?string $note = null)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->warehouse = $warehouse;
        $this->product = $product;
        $this->user = $user;
        $this->type = $type;
        $this->quantityDelta = $quantityDelta;
        $this->quantityAfter = $quantityAfter;
        $this->note = $note;
        $this->createdAt = new \DateTimeImmutable();
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
    public function getType(): string
    {
        return $this->type;
    }
    public function getQuantityDelta(): string
    {
        return $this->quantityDelta;
    }
    public function getQuantityAfter(): string
    {
        return $this->quantityAfter;
    }
    public function getNote(): ?string
    {
        return $this->note;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
