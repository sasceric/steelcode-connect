<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'idx_product_tenant_status_updated', columns: ['tenant_id', 'status', 'updated_at'])]
#[ORM\Index(name: 'idx_product_tenant_parent_updated', columns: ['tenant_id', 'parent_id', 'updated_at'])]
#[ORM\Index(name: 'idx_product_tenant_parent_sku', columns: ['tenant_id', 'parent_id', 'sku'])]
#[ORM\Index(name: 'idx_product_tenant_manufacturer_parent', columns: ['tenant_id', 'manufacturer_id', 'parent_id'])]
#[ORM\Index(name: 'idx_product_parent_created', columns: ['parent_id', 'created_at'])]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ean = null;

    #[ORM\Column(type: 'json')]
    private array $optionValues = [];

    #[ORM\Column(length: 32)] private string $productType = 'physical';
    #[ORM\Column(length: 255, nullable: true)] private ?string $manufacturerNumber = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tax $tax = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Unit $unit = null;
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $purchaseUnit = null;
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $referenceUnit = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $packUnit = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $packUnitPlural = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $shippingClass = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $deliveryTime = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'delivery_time_id', nullable: true, onDelete: 'SET NULL')]
    private ?DeliveryTime $deliveryTimeReference = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $releaseDate = null;
    #[ORM\Column] private bool $isFeatured = false;
    #[ORM\Column(type: 'json')] private array $visibility = [];
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)] private string $minPurchaseQuantity = '1.0000';
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)] private string $purchaseSteps = '1.0000';
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)] private ?string $maxPurchaseQuantity = null;
    #[ORM\Column(nullable: true)] private ?int $restockTimeDays = null;
    #[ORM\Column] private bool $clearanceSale = false;
    #[ORM\Column] private bool $freeShipping = false;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $searchKeywords = null;
    #[ORM\Column(nullable: true)] private ?int $weightGrams = null;
    #[ORM\Column(nullable: true)] private ?int $lengthMillimeters = null;
    #[ORM\Column(nullable: true)] private ?int $widthMillimeters = null;
    #[ORM\Column(nullable: true)] private ?int $heightMillimeters = null;
    #[ORM\Column(type: 'json')] private array $price = [];
    #[ORM\Column(type: 'json')] private array $purchasePrice = [];
    #[ORM\Column(type: 'json')] private array $cheapestPrice = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Manufacturer $manufacturer = null;

    #[ORM\Column(length: 16)]
    private string $status = 'draft';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
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
    public function getParent(): ?self
    {
        return $this->parent;
    }
    public function getSku(): ?string
    {
        return $this->sku;
    }
    public function getEan(): ?string
    {
        return $this->ean;
    }
    public function getOptionValues(): array
    {
        return $this->optionValues;
    }
    public function getProductType(): string
    {
        return $this->productType;
    }
    public function getManufacturerNumber(): ?string
    {
        return $this->manufacturerNumber;
    }
    public function getTax(): ?Tax
    {
        return $this->tax;
    }
    public function getUnit(): ?Unit
    {
        return $this->unit;
    }
    public function getPurchaseUnit(): ?string
    {
        return $this->purchaseUnit;
    }
    public function getReferenceUnit(): ?string
    {
        return $this->referenceUnit;
    }
    public function getPackUnit(): ?string
    {
        return $this->packUnit;
    }
    public function getPackUnitPlural(): ?string
    {
        return $this->packUnitPlural;
    }
    public function getShippingClass(): ?string
    {
        return $this->shippingClass;
    }
    public function getDeliveryTime(): ?string
    {
        return $this->deliveryTime;
    }
    public function getDeliveryTimeReference(): ?DeliveryTime
    {
        return $this->deliveryTimeReference;
    }
    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }
    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }
    public function getVisibility(): array
    {
        return $this->visibility;
    }
    public function getMinPurchaseQuantity(): string
    {
        return $this->minPurchaseQuantity;
    }
    public function getPurchaseSteps(): string
    {
        return $this->purchaseSteps;
    }
    public function getMaxPurchaseQuantity(): ?string
    {
        return $this->maxPurchaseQuantity;
    }
    public function getRestockTimeDays(): ?int
    {
        return $this->restockTimeDays;
    }
    public function isClearanceSale(): bool
    {
        return $this->clearanceSale;
    }
    public function isFreeShipping(): bool
    {
        return $this->freeShipping;
    }
    public function getSearchKeywords(): ?string
    {
        return $this->searchKeywords;
    }
    public function getWeightGrams(): ?int
    {
        return $this->weightGrams;
    }
    public function getLengthMillimeters(): ?int
    {
        return $this->lengthMillimeters;
    }
    public function getWidthMillimeters(): ?int
    {
        return $this->widthMillimeters;
    }
    public function getHeightMillimeters(): ?int
    {
        return $this->heightMillimeters;
    }
    public function getPrice(): array
    {
        return $this->price;
    }
    public function getPurchasePrice(): array
    {
        return $this->purchasePrice;
    }
    public function getCheapestPrice(): array
    {
        return $this->cheapestPrice;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function getManufacturer(): ?Manufacturer
    {
        return $this->manufacturer;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateSku(?string $sku): void
    {
        $this->sku = $sku;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateIdentity(?string $sku, ?string $ean): void
    {
        $this->sku = $sku;
        $this->ean = $ean;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @param array<string, string> $optionValues */
    public function makeChildOf(self $parent, string $sku, ?string $ean, array $optionValues): void
    {
        $this->parent = $parent;
        $this->sku = $sku;
        $this->ean = $ean;
        $this->optionValues = $optionValues;
    }
    public function inheritCatalogDataFrom(self $parent): void
    {
        $this->manufacturer = $parent->manufacturer;
        $this->productType = $parent->productType;
        $this->manufacturerNumber = $parent->manufacturerNumber;
        $this->tax = $parent->tax;
        $this->shippingClass = $parent->shippingClass;
        $this->deliveryTime = $parent->deliveryTime;
        $this->releaseDate = $parent->releaseDate;
        $this->isFeatured = $parent->isFeatured;
        $this->visibility = $parent->visibility;
        $this->minPurchaseQuantity = $parent->minPurchaseQuantity;
        $this->purchaseSteps = $parent->purchaseSteps;
        $this->maxPurchaseQuantity = $parent->maxPurchaseQuantity;
        $this->restockTimeDays = $parent->restockTimeDays;
        $this->clearanceSale = $parent->clearanceSale;
        $this->freeShipping = $parent->freeShipping;
        $this->searchKeywords = $parent->searchKeywords;
        $this->weightGrams = $parent->weightGrams;
        $this->lengthMillimeters = $parent->lengthMillimeters;
        $this->widthMillimeters = $parent->widthMillimeters;
        $this->heightMillimeters = $parent->heightMillimeters;
        $this->price = $parent->price;
        $this->purchasePrice = $parent->purchasePrice;
        $this->cheapestPrice = $parent->cheapestPrice;
        $this->status = $parent->status;
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function updateCommerce(string $productType, ?string $manufacturerNumber, ?string $shippingClass, ?string $deliveryTime, ?\DateTimeImmutable $releaseDate, bool $isFeatured, array $visibility): void
    {
        $this->productType = $productType;
        $this->manufacturerNumber = $manufacturerNumber;
        $this->shippingClass = $shippingClass;
        $this->deliveryTime = $deliveryTime;
        $this->releaseDate = $releaseDate;
        $this->isFeatured = $isFeatured;
        $this->visibility = $visibility;
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function updateFulfilment(string $minPurchaseQuantity, string $purchaseSteps, ?string $maxPurchaseQuantity, ?int $restockTimeDays, bool $clearanceSale, bool $freeShipping, ?string $searchKeywords, ?int $weightGrams, ?int $lengthMillimeters, ?int $widthMillimeters, ?int $heightMillimeters): void
    {
        $this->minPurchaseQuantity = $minPurchaseQuantity;
        $this->purchaseSteps = $purchaseSteps;
        $this->maxPurchaseQuantity = $maxPurchaseQuantity;
        $this->restockTimeDays = $restockTimeDays;
        $this->clearanceSale = $clearanceSale;
        $this->freeShipping = $freeShipping;
        $this->searchKeywords = $searchKeywords;
        $this->weightGrams = $weightGrams;
        $this->lengthMillimeters = $lengthMillimeters;
        $this->widthMillimeters = $widthMillimeters;
        $this->heightMillimeters = $heightMillimeters;
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function updatePrices(array $price, array $purchasePrice, array $cheapestPrice): void
    {
        $this->price = $price;
        $this->purchasePrice = $purchasePrice;
        $this->cheapestPrice = $cheapestPrice;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateReferences(
        ?Tax $tax,
        ?Unit $unit,
        ?string $purchaseUnit,
        ?string $referenceUnit,
        ?DeliveryTime $deliveryTime,
    ): void {
        $this->tax = $tax;
        $this->unit = $unit;
        $this->purchaseUnit = $purchaseUnit;
        $this->referenceUnit = $referenceUnit;
        $this->deliveryTimeReference = $deliveryTime;
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function updatePackUnits(?string $packUnit, ?string $packUnitPlural): void
    {
        $this->packUnit = $packUnit;
        $this->packUnitPlural = $packUnitPlural;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateManufacturer(?Manufacturer $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
