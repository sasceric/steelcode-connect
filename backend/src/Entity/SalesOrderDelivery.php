<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sales_order_deliveries')]
#[ORM\UniqueConstraint(name: 'uniq_sales_order_delivery_external', columns: ['sales_order_id', 'external_id'])]
class SalesOrderDelivery
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'deliveries')]
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(type: 'json')]
    private array $trackingCodes = [];

    #[ORM\Column(type: 'json')]
    private array $positionsSnapshot = [];

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $shippingGross = null;

    #[ORM\Column(type: 'json')]
    private array $shippingAddressSnapshot = [];

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

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    /** @return list<string> */
    public function getTrackingCodes(): array
    {
        return $this->trackingCodes;
    }

    /** @return list<array<string, mixed>> */
    public function getPositionsSnapshot(): array
    {
        return $this->positionsSnapshot;
    }

    public function getShippingGross(): ?string
    {
        return $this->shippingGross;
    }

    /** @return array<string, mixed> */
    public function getShippingAddressSnapshot(): array
    {
        return $this->shippingAddressSnapshot;
    }

    /** @return array<string, mixed> */
    public function getSourcePayload(): array
    {
        return $this->sourcePayload;
    }

    /**
     * @param array<string, mixed> $shippingAddressSnapshot
     * @param array<string, mixed> $sourcePayload
     */
    public function snapshot(
        ?string $methodExternalId,
        ?string $methodName,
        ?string $state,
        array $trackingCodes,
        array $positionsSnapshot,
        ?string $shippingGross,
        array $shippingAddressSnapshot,
        array $sourcePayload,
    ): void {
        $this->methodExternalId = $methodExternalId;
        $this->methodName = $methodName;
        $this->state = $state;
        $this->trackingCodes = $trackingCodes;
        $this->trackingNumber = $trackingCodes[0] ?? null;
        $this->positionsSnapshot = $positionsSnapshot;
        $this->shippingGross = $shippingGross;
        $this->shippingAddressSnapshot = $shippingAddressSnapshot;
        $this->sourcePayload = $sourcePayload;
    }
}
