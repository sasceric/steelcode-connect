<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'inventory_sync_outbox')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_sync_outbox_product', columns: ['tenant_id', 'product_id'])]
class InventorySyncOutbox
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 24)]
    private string $status = 'pending';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, Product $product)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function queue(): void
    {
        $this->status = 'pending';
        $this->lastError = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markDispatched(): void
    {
        $this->status = 'dispatched';
        $this->lastError = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markFailed(string $reason): void
    {
        $this->status = 'failed';
        $this->lastError = mb_substr($reason, 0, 2000);
        $this->updatedAt = new \DateTimeImmutable();
    }
}
