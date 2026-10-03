<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sales_orders')]
#[ORM\UniqueConstraint(name: 'uniq_sales_order_connection_external', columns: ['connection_id', 'external_id'])]
#[ORM\Index(name: 'idx_sales_order_tenant_status_created', columns: ['tenant_id', 'status', 'created_at'])]
#[ORM\Index(name: 'idx_sales_order_connection_status_id', columns: ['connection_id', 'status', 'id'])]
class SalesOrder
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IntegrationConnection $connection;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 128)]
    private string $externalId;

    #[ORM\Column(length: 255)]
    private string $externalNumber;

    #[ORM\Column(length: 32)]
    private string $status = 'new';

    #[ORM\Column(type: 'json')]
    private array $customerSnapshot = [];

    #[ORM\Column(type: 'json')]
    private array $billingAddressSnapshot = [];

    #[ORM\Column(type: 'json')]
    private array $shippingAddressSnapshot = [];

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currencyCode = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $taxStatus = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $amountNet = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $amountTax = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $amountGross = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $shippingNet = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $shippingTax = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $shippingGross = null;

    #[ORM\Column(type: 'json')]
    private array $sourcePayload = [];

    /** @var array<string, string> */
    #[ORM\Column(type: 'json')]
    private array $manualShipmentTargets = [];

    /** @var Collection<int, SalesOrderItem> */
    #[ORM\OneToMany(mappedBy: 'salesOrder', targetEntity: SalesOrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    /** @var Collection<int, SalesOrderPayment> */
    #[ORM\OneToMany(mappedBy: 'salesOrder', targetEntity: SalesOrderPayment::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $payments;

    /** @var Collection<int, SalesOrderDelivery> */
    #[ORM\OneToMany(mappedBy: 'salesOrder', targetEntity: SalesOrderDelivery::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $deliveries;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $orderedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $externalId,
        string $externalNumber,
        array $sourcePayload = [],
        ?\DateTimeImmutable $orderedAt = null,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->connection = $connection;
        $this->externalId = $externalId;
        $this->externalNumber = $externalNumber;
        $this->sourcePayload = $sourcePayload;
        $this->orderedAt = $orderedAt;
        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->deliveries = new ArrayCollection();
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

    public function getConnection(): IntegrationConnection
    {
        return $this->connection;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getExternalNumber(): string
    {
        return $this->externalNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function getSourcePayload(): array
    {
        return $this->sourcePayload;
    }

    /** @return array<string, string> */
    public function getManualShipmentTargets(): array
    {
        return $this->manualShipmentTargets;
    }

    /** @param array<string, string> $targets */
    public function setManualShipmentTargets(array $targets): void
    {
        $this->manualShipmentTargets = $targets;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function needsShipmentReconciliation(): bool
    {
        if (($this->sourcePayload['shipmentQuantitiesUnavailable'] ?? false) === true) {
            return true;
        }
        $allShipped = !$this->deliveries->isEmpty();
        $missingPositions = false;
        $hasPositionDetails = false;
        foreach ($this->deliveries as $delivery) {
            if ($delivery->getState() === 'shipped_partially') {
                return true;
            }
            if ($delivery->getState() !== 'shipped') {
                $allShipped = false;
                continue;
            }
            if ($delivery->getPositionsSnapshot() === []) {
                $missingPositions = true;
            } else {
                $hasPositionDetails = true;
            }
        }

        return $missingPositions && (!$allShipped || $hasPositionDetails);
    }

    public function getSourceStatus(): ?string
    {
        $state = $this->sourcePayload['state'] ?? null;

        return is_string($state) && trim($state) !== '' ? $state : null;
    }

    /** @return list<string> */
    public function getUnresolvedSkus(): array
    {
        $skus = [];
        foreach ($this->items as $item) {
            if (!$item->isProductResolved()) {
                $skus[] = $item->getSku() ?? 'line '.$item->getExternalLineId();
            }
        }

        return array_values(array_unique($skus));
    }

    /** @param array<string, mixed> $sourcePayload */
    public function updateSourcePayload(array $sourcePayload): void
    {
        $this->sourcePayload = $sourcePayload;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markHistorical(): void
    {
        if ($this->status !== 'new') {
            return;
        }

        foreach ($this->items as $item) {
            if (!$item->getAllocations()->isEmpty()) {
                throw new \DomainException('An allocated order cannot become historical.');
            }
        }

        $this->status = 'historical';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateHistoricalState(string $state): void
    {
        if ($this->status !== 'historical') {
            throw new \DomainException('Only historical orders can update their source state without stock operations.');
        }

        $this->sourcePayload['state'] = $state;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getOrderedAt(): ?\DateTimeImmutable
    {
        return $this->orderedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return array<string, mixed> */
    public function getCustomerSnapshot(): array
    {
        return $this->customerSnapshot;
    }

    /** @return array<string, mixed> */
    public function getBillingAddressSnapshot(): array
    {
        return $this->billingAddressSnapshot;
    }

    /** @return array<string, mixed> */
    public function getShippingAddressSnapshot(): array
    {
        return $this->shippingAddressSnapshot;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function getTaxStatus(): ?string
    {
        return $this->taxStatus;
    }

    public function getAmountNet(): ?string
    {
        return $this->amountNet;
    }

    public function getAmountTax(): ?string
    {
        return $this->amountTax;
    }

    public function getAmountGross(): ?string
    {
        return $this->amountGross;
    }

    public function getShippingNet(): ?string
    {
        return $this->shippingNet;
    }

    public function getShippingTax(): ?string
    {
        return $this->shippingTax;
    }

    public function getShippingGross(): ?string
    {
        return $this->shippingGross;
    }

    /**
     * Stores values as received from checkout. They are never recalculated from
     * current catalogue prices, tax rules, or customer details.
     *
     * @param array<string, mixed> $customerSnapshot
     * @param array<string, mixed> $billingAddressSnapshot
     * @param array<string, mixed> $shippingAddressSnapshot
     */
    public function setCommercialSnapshot(
        ?Customer $customer,
        array $customerSnapshot,
        array $billingAddressSnapshot,
        array $shippingAddressSnapshot,
        ?string $currencyCode,
        ?string $taxStatus,
        ?string $amountNet,
        ?string $amountTax,
        ?string $amountGross,
        ?string $shippingNet,
        ?string $shippingTax,
        ?string $shippingGross,
    ): void {
        $this->customer = $customer;
        $this->customerSnapshot = $customerSnapshot;
        $this->billingAddressSnapshot = $billingAddressSnapshot;
        $this->shippingAddressSnapshot = $shippingAddressSnapshot;
        $this->currencyCode = $currencyCode;
        $this->taxStatus = $taxStatus;
        $this->amountNet = $amountNet;
        $this->amountTax = $amountTax;
        $this->amountGross = $amountGross;
        $this->shippingNet = $shippingNet;
        $this->shippingTax = $shippingTax;
        $this->shippingGross = $shippingGross;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, SalesOrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /** @return Collection<int, SalesOrderPayment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    /** @return Collection<int, SalesOrderDelivery> */
    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    public function getOutstandingQuantity(): string
    {
        return number_format(
            array_reduce(
                $this->items->toArray(),
                static fn (float $total, SalesOrderItem $item): float => $total + ($item->isInventoryLine() ? (float) $item->getOpenQuantity() : 0),
                0,
            ),
            4,
            '.',
            '',
        );
    }

    public function addItem(
        ?Product $product,
        string $externalLineId,
        ?string $sku,
        string $name,
        string $quantity,
        string $lineType = 'product',
        array $sourcePayload = [],
    ): SalesOrderItem {
        $item = new SalesOrderItem(
            $this,
            $product,
            $externalLineId,
            $sku,
            $name,
            $quantity,
            $lineType,
            $sourcePayload,
        );
        $this->items->add($item);
        $this->updatedAt = new \DateTimeImmutable();

        return $item;
    }

    public function removeHistoricalItem(SalesOrderItem $item): void
    {
        if ($this->status !== 'historical' || !$item->getAllocations()->isEmpty()) {
            throw new \DomainException('Only an unallocated historical line can be replaced.');
        }

        $this->items->removeElement($item);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function removeUnfulfilledItem(SalesOrderItem $item): void
    {
        if ((float) $item->getReservedQuantity() > 0.00001 || (float) $item->getFulfilledQuantity() > 0.00001) {
            throw new \DomainException('A reserved or shipped order line cannot be removed.');
        }

        $this->items->removeElement($item);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addPayment(string $externalId): SalesOrderPayment
    {
        $payment = new SalesOrderPayment($this, $externalId);
        $this->payments->add($payment);

        return $payment;
    }

    public function addDelivery(string $externalId): SalesOrderDelivery
    {
        $delivery = new SalesOrderDelivery($this, $externalId);
        $this->deliveries->add($delivery);

        return $delivery;
    }

    public function markReserved(): void
    {
        if ($this->status !== 'new') {
            throw new \DomainException('Only new orders can be reserved.');
        }
        $this->status = 'reserved';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markCancelled(): void
    {
        if (!in_array($this->status, ['new', 'reserved', 'partially_fulfilled'], true)) {
            throw new \DomainException('This order cannot be cancelled.');
        }
        $this->status = 'cancelled';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markFulfilled(): void
    {
        $this->status = 'fulfilled';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markPartiallyFulfilled(): void
    {
        if (!in_array($this->status, ['reserved', 'partially_fulfilled'], true)) {
            throw new \DomainException('Only reserved orders can be partially fulfilled.');
        }
        $this->status = 'partially_fulfilled';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function refreshInventoryStatus(): void
    {
        if (in_array($this->status, ['historical', 'cancelled'], true)) {
            return;
        }

        $fulfilled = 0.0;
        $reserved = 0.0;
        $remaining = 0.0;
        foreach ($this->items as $item) {
            if (!$item->isInventoryLine()) {
                continue;
            }
            $fulfilled += (float) $item->getFulfilledQuantity();
            $reserved += (float) $item->getReservedQuantity();
            $remaining += (float) $item->getQuantity() - (float) $item->getFulfilledQuantity();
        }

        $this->status = $fulfilled > 0.00001
            ? ($remaining <= 0.00001 ? 'fulfilled' : 'partially_fulfilled')
            : ($reserved > 0.00001 ? 'reserved' : 'new');
        $this->updatedAt = new \DateTimeImmutable();
    }
}
