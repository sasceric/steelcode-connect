<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'supplier_offers')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_offer_product', columns: ['tenant_id', 'supplier_id', 'product_id'])]
class SupplierOffer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $supplierSku = null;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $unitCost = '0.0000';

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $minimumQuantity = '1.0000';

    #[ORM\Column(length: 64)]
    private string $purchaseUnit = 'unit';

    #[ORM\Column]
    private int $stockUnitsPerPurchaseUnit = 1;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadTimeDays = null;

    #[ORM\Column]
    private bool $preferred = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, Supplier $supplier, Product $product)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->supplier = $supplier;
        $this->product = $product;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(
        ?string $supplierSku,
        string $unitCost,
        string $currency,
        string $minimumQuantity,
        ?int $leadTimeDays,
        bool $preferred,
        bool $active,
    ): void {
        $this->supplierSku = $supplierSku;
        $this->unitCost = $unitCost;
        $this->currency = $currency;
        $this->minimumQuantity = $minimumQuantity;
        $this->leadTimeDays = $leadTimeDays;
        $this->preferred = $preferred;
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getSupplierSku(): ?string
    {
        return $this->supplierSku;
    }

    public function getUnitCost(): string
    {
        return $this->unitCost;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getMinimumQuantity(): string
    {
        return $this->minimumQuantity;
    }

    public function getLeadTimeDays(): ?int
    {
        return $this->leadTimeDays;
    }

    public function setPurchaseUnit(string $purchaseUnit, int $stockUnitsPerPurchaseUnit): void
    {
        $this->purchaseUnit = $purchaseUnit;
        $this->stockUnitsPerPurchaseUnit = $stockUnitsPerPurchaseUnit;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPurchaseUnit(): string
    {
        return $this->purchaseUnit;
    }

    public function getStockUnitsPerPurchaseUnit(): int
    {
        return $this->stockUnitsPerPurchaseUnit;
    }

    public function setValidity(?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validUntil): void
    {
        $this->validFrom = $validFrom;
        $this->validUntil = $validUntil;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function isCurrentlyValid(): bool
    {
        $today = new \DateTimeImmutable('today');

        return ($this->validFrom === null || $this->validFrom <= $today)
            && ($this->validUntil === null || $this->validUntil >= $today);
    }

    public function isPreferred(): bool
    {
        return $this->preferred;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
