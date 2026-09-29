<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_prices')]
#[ORM\UniqueConstraint(name: 'uniq_product_price_tier', columns: ['tenant_id', 'product_id', 'currency_id', 'price_type', 'pricing_context', 'quantity_start'])]
class ProductPrice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Currency $currency;

    #[ORM\Column(length: 16)]
    private string $priceType;

    #[ORM\Column(type: 'json')]
    private array $price;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $taxRate;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantityStart = '1.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4, nullable: true)]
    private ?string $quantityEnd = null;

    #[ORM\Column(length: 64)]
    private string $pricingContext = 'default';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(length: 16)]
    private string $source = 'manual';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, Product $product, Currency $currency, string $priceType, array $price, string $taxRate)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->currency = $currency;
        $this->priceType = $priceType;
        $this->price = $price;
        $this->taxRate = $taxRate;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getProduct(): Product
    {
        return $this->product;
    }
    public function getCurrency(): Currency
    {
        return $this->currency;
    }
    public function getPriceType(): string
    {
        return $this->priceType;
    }
    public function getPrice(): array
    {
        return $this->price;
    }
    public function getTaxRate(): string
    {
        return $this->taxRate;
    }
    public function getQuantityStart(): string
    {
        return $this->quantityStart;
    }
    public function getQuantityEnd(): ?string
    {
        return $this->quantityEnd;
    }
    public function getPricingContext(): string
    {
        return $this->pricingContext;
    }
    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }
    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function updateAdvanced(
        string $quantityStart,
        ?string $quantityEnd,
        string $pricingContext,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
        string $source = 'manual',
    ): void
    {
        $this->quantityStart = $quantityStart;
        $this->quantityEnd = $quantityEnd;
        $this->pricingContext = $pricingContext;
        $this->validFrom = $validFrom;
        $this->validUntil = $validUntil;
        $this->source = $source;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updatePrice(array $price, string $taxRate): void
    {
        $this->price = $price;
        $this->taxRate = $taxRate;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
