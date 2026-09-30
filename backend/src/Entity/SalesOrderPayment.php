<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sales_order_payments')]
#[ORM\UniqueConstraint(name: 'uniq_sales_order_payment_external', columns: ['sales_order_id', 'external_id'])]
class SalesOrderPayment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SalesOrder $salesOrder;

    #[ORM\Column(length: 128)]
    private string $externalId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $methodName = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $methodExternalId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currencyCode = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(type: 'json')]
    private array $sourcePayload = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(SalesOrder $salesOrder, string $externalId)
    {
        $this->id = Uuid::v7();
        $this->salesOrder = $salesOrder;
        $this->externalId = $externalId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getMethodName(): ?string
    {
        return $this->methodName;
    }

    public function getMethodExternalId(): ?string
    {
        return $this->methodExternalId;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    /** @return array<string, mixed> */
    public function getSourcePayload(): array
    {
        return $this->sourcePayload;
    }

    /** @param array<string, mixed> $sourcePayload */
    public function snapshot(
        ?string $methodExternalId,
        ?string $methodName,
        ?string $state,
        ?string $reference,
        ?string $currencyCode,
        ?string $amount,
        array $sourcePayload,
    ): void {
        $this->methodExternalId = $methodExternalId;
        $this->methodName = $methodName;
        $this->state = $state;
        $this->reference = $reference;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->sourcePayload = $sourcePayload;
    }
}
