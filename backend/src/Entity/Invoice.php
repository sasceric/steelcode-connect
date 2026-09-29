<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'invoices')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_tenant_period', columns: ['tenant_id', 'billing_period'])]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 64, unique: true)]
    private string $number;

    #[ORM\Column(length: 32)]
    private string $plan;

    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 3)]
    private string $currency = 'BAM';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $billingPeriod;

    #[ORM\Column(length: 16)]
    private string $billingInterval;

    #[ORM\Column(length: 32)]
    private string $status = 'open';

    #[ORM\Column]
    private \DateTimeImmutable $issuedAt;

    public function __construct(Tenant $tenant, string $number, string $plan, int $amount, \DateTimeImmutable $billingPeriod, string $billingInterval)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->number = $number;
        $this->plan = $plan;
        $this->amount = $amount;
        $this->billingPeriod = $billingPeriod->setTime(0, 0);
        $this->billingInterval = $billingInterval;
        $this->issuedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getTenant(): Tenant
    {
        return $this->tenant;
    }
    public function getNumber(): string
    {
        return $this->number;
    }
    public function getPlan(): string
    {
        return $this->plan;
    }
    public function getAmount(): int
    {
        return $this->amount;
    }
    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function getBillingPeriod(): \DateTimeImmutable
    {
        return $this->billingPeriod;
    }
    public function getBillingInterval(): string
    {
        return $this->billingInterval;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }
}
