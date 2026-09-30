<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sales_order_items')]
#[ORM\UniqueConstraint(name: 'uniq_sales_order_external_line', columns: ['sales_order_id', 'external_line_id'])]
class SalesOrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SalesOrder $salesOrder;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Product $product;

    #[ORM\Column(length: 128)]
    private string $externalLineId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sku;

    #[ORM\Column(length: 64)]
    private string $lineType = 'product';

    #[ORM\Column(type: 'json')]
    private array $sourcePayload = [];

    #[ORM\Column(length: 512)]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantity;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currencyCode = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $unitNet = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $unitGross = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $totalNet = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $totalTax = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $totalGross = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $discountGross = null;

    #[ORM\Column(type: 'json')]
    private array $taxSnapshot = [];

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $reservedQuantity = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $fulfilledQuantity = '0.0000';

    /** @var Collection<int, SalesOrderAllocation> */
    #[ORM\OneToMany(mappedBy: 'salesOrderItem', targetEntity: SalesOrderAllocation::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $allocations;

    public function __construct(
        SalesOrder $salesOrder,
        ?Product $product,
        string $externalLineId,
        ?string $sku,
        string $name,
        string $quantity,
        string $lineType = 'product',
        array $sourcePayload = [],
    ) {
        $this->id = Uuid::v7();
        $this->salesOrder = $salesOrder;
        $this->product = $product;
        $this->externalLineId = $externalLineId;
        $this->sku = $sku;
        $this->lineType = $lineType;
        $this->sourcePayload = $sourcePayload;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->allocations = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function getSourcePayload(): array
    {
        return $this->sourcePayload;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function isProductResolved(): bool
    {
        return !$this->isInventoryLine() || $this->product instanceof Product;
    }

    public function isInventoryLine(): bool
    {
        return $this->lineType === 'product';
    }

    public function getLineType(): string
    {
        return $this->lineType;
    }

    public function getExternalLineId(): string
    {
        return $this->externalLineId;
    }

    public function getSalesOrder(): SalesOrder
    {
        return $this->salesOrder;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    /** @param array<string, mixed> $sourcePayload */
    public function refreshSnapshot(
        ?Product $product,
        ?string $sku,
        string $name,
        string $quantity,
        string $lineType,
        array $sourcePayload,
    ): void {
        if (!$this->allocations->isEmpty()) {
            throw new \DomainException('An allocated order line cannot be rewritten by a historical import.');
        }

        $this->product = $product;
        $this->sku = $sku;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->lineType = $lineType;
        $this->sourcePayload = $sourcePayload;
    }

    /** @param array<string, mixed> $sourcePayload */
    public function refreshLiveSnapshot(
        ?Product $product,
        ?string $sku,
        string $name,
        string $quantity,
        string $lineType,
        array $sourcePayload,
    ): void {
        if ((float) $this->reservedQuantity > 0.00001) {
            throw new \DomainException('Release the existing reservation before editing an order line.');
        }
        if (
            (float) $this->fulfilledQuantity > 0.00001
            && (
                $this->sku !== $sku
                || $this->lineType !== $lineType
                || (float) $quantity + 0.00001 < (float) $this->fulfilledQuantity
            )
        ) {
            throw new \DomainException('A shipped order line cannot change product or fall below its shipped quantity.');
        }

        $this->product = $product;
        $this->sku = $sku;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->lineType = $lineType;
        $this->sourcePayload = $sourcePayload;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @param array<string, mixed> $sourcePayload */
    public function updateDisplaySnapshot(string $name, array $sourcePayload): void
    {
        $this->name = $name;
        $this->sourcePayload = $sourcePayload;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function getUnitGross(): ?string
    {
        return $this->unitGross;
    }

    public function getTotalTax(): ?string
    {
        return $this->totalTax;
    }

    public function getTotalGross(): ?string
    {
        return $this->totalGross;
    }

    /** @return array<string, mixed> */
    public function getTaxSnapshot(): array
    {
        return $this->taxSnapshot;
    }

    /** @param array<string, mixed> $taxSnapshot */
    public function setCommercialSnapshot(
        ?string $currencyCode,
        ?string $unitNet,
        ?string $unitGross,
        ?string $totalNet,
        ?string $totalTax,
        ?string $totalGross,
        ?string $discountGross,
        array $taxSnapshot,
    ): void {
        $this->currencyCode = $currencyCode;
        $this->unitNet = $unitNet;
        $this->unitGross = $unitGross;
        $this->totalNet = $totalNet;
        $this->totalTax = $totalTax;
        $this->totalGross = $totalGross;
        $this->discountGross = $discountGross;
        $this->taxSnapshot = $taxSnapshot;
    }

    public function getOpenQuantity(): string
    {
        return number_format(
            (float) $this->quantity - (float) $this->reservedQuantity - (float) $this->fulfilledQuantity,
            4,
            '.',
            '',
        );
    }

    public function getReservedQuantity(): string
    {
        return $this->reservedQuantity;
    }

    public function getFulfilledQuantity(): string
    {
        return $this->fulfilledQuantity;
    }

    /** @return Collection<int, SalesOrderAllocation> */
    public function getAllocations(): Collection
    {
        return $this->allocations;
    }

    public function addAllocation(Warehouse $warehouse, string $quantity): SalesOrderAllocation
    {
        $allocation = new SalesOrderAllocation($this, $warehouse, $quantity);
        $this->allocations->add($allocation);
        $this->reservedQuantity = number_format((float) $this->reservedQuantity + (float) $quantity, 4, '.', '');

        return $allocation;
    }

    public function addSplitAllocation(SalesOrderAllocation $allocation): void
    {
        if ($allocation->getSalesOrderItem() !== $this || $allocation->getStatus() !== 'shipped') {
            throw new \LogicException('Only a shipped split of this order line can be added.');
        }

        $this->allocations->add($allocation);
    }

    public function release(string $quantity): void
    {
        if ((float) $quantity <= 0 || (float) $this->reservedQuantity + 0.00001 < (float) $quantity) {
            throw new \DomainException('The sales-order reservation cannot be released.');
        }
        $this->reservedQuantity = number_format((float) $this->reservedQuantity - (float) $quantity, 4, '.', '');
    }

    public function fulfill(string $quantity): void
    {
        if ((float) $quantity <= 0 || (float) $this->reservedQuantity + 0.00001 < (float) $quantity) {
            throw new \DomainException('The sales-order reservation cannot be fulfilled.');
        }
        $this->reservedQuantity = number_format((float) $this->reservedQuantity - (float) $quantity, 4, '.', '');
        $this->fulfilledQuantity = number_format((float) $this->fulfilledQuantity + (float) $quantity, 4, '.', '');
    }
}
