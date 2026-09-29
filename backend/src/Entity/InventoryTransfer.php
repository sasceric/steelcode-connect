<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'inventory_transfers')]
#[ORM\Index(name: 'idx_inventory_transfers_tenant_status', columns: ['tenant_id', 'status'])]
class InventoryTransfer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Warehouse $sourceWarehouse;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Warehouse $destinationWarehouse;

    #[ORM\Column(length: 16)]
    private string $status = 'draft';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    /** @var Collection<int, InventoryTransferItem> */
    #[ORM\OneToMany(mappedBy: 'transfer', targetEntity: InventoryTransferItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    public function __construct(
        Tenant $tenant,
        Warehouse $sourceWarehouse,
        Warehouse $destinationWarehouse,
        ?string $note = null,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->sourceWarehouse = $sourceWarehouse;
        $this->destinationWarehouse = $destinationWarehouse;
        $this->note = $note;
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getSourceWarehouse(): Warehouse
    {
        return $this->sourceWarehouse;
    }

    public function getDestinationWarehouse(): Warehouse
    {
        return $this->destinationWarehouse;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    /** @return Collection<int, InventoryTransferItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(Product $product, string $quantity): void
    {
        $this->items->add(new InventoryTransferItem($this, $product, $quantity));
    }

    public function markSent(): void
    {
        $this->status = 'in_transit';
        $this->sentAt = new \DateTimeImmutable();
    }

    public function markReceived(): void
    {
        $this->status = 'received';
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
