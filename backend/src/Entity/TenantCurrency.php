<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_currencies')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_currency', columns: ['tenant_id', 'currency_id'])]
class TenantCurrency
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Currency $currency;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, Currency $currency, bool $isDefault = false)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->currency = $currency;
        $this->isDefault = $isDefault;
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
    public function getCurrency(): Currency
    {
        return $this->currency;
    }
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    public function isDefault(): bool
    {
        return $this->isDefault;
    }
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
    public function setDefault(bool $default): void
    {
        $this->isDefault = $default;
    }
}
