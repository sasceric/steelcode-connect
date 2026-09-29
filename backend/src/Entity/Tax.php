<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'taxes')]
#[ORM\UniqueConstraint(name: 'uniq_tax_tenant_name', columns: ['tenant_id', 'name'])]
class Tax
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $rate;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(Tenant $tenant, string $name, string $rate)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->name = $name;
        $this->rate = $rate;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function update(string $name, string $rate, bool $active): void
    {
        $this->name = $name;
        $this->rate = $rate;
        $this->active = $active;
    }
}
