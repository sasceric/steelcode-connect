<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'inventory_counts')]
#[ORM\Index(name: 'idx_inventory_counts_tenant_status', columns: ['tenant_id', 'status'])]
class InventoryCount
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Warehouse $warehouse;

    #[ORM\Column(length: 16)]
    private string $status = 'draft';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    /** @var Collection<int, InventoryCountItem> */
    #[ORM\OneToMany(mappedBy: 'count', targetEntity: InventoryCountItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $postedAt = null;

    public function __construct(Tenant $tenant, Warehouse $warehouse, ?string $note = null)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->warehouse = $warehouse;
        $this->note = $note;
        $this->items = new ArrayCollection();
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    /** @return Collection<int, InventoryCountItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(Product $product, string $expectedQuantity, ?int $expectedVersion, string $countedQuantity): void
    {
        $this->items->add(new InventoryCountItem($this, $product, $expectedQuantity, $expectedVersion, $countedQuantity));
    }

    public function post(): void
    {
        $this->status = 'posted';
        $this->postedAt = new \DateTimeImmutable();
    }
}
