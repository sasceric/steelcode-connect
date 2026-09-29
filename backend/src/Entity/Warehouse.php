<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'warehouses')]
#[ORM\UniqueConstraint(name: 'uniq_warehouse_tenant_code', columns: ['tenant_id', 'code'])]
class Warehouse
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private bool $fulfillmentEnabled = true;

    #[ORM\Column]
    private int $priority = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Tenant $tenant,
        string $code,
        string $name,
        bool $fulfillmentEnabled = true,
        int $priority = 0,
    )
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->code = $code;
        $this->name = $name;
        $this->fulfillmentEnabled = $fulfillmentEnabled;
        $this->priority = max(0, $priority);
        $this->createdAt = new \DateTimeImmutable();
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isFulfillmentEnabled(): bool
    {
        return $this->fulfillmentEnabled;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function update(
        string $code,
        string $name,
        bool $active,
        bool $fulfillmentEnabled,
        int $priority,
    ): void
    {
        $this->code = $code;
        $this->name = $name;
        $this->active = $active;
        $this->fulfillmentEnabled = $fulfillmentEnabled;
        $this->priority = $priority;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
