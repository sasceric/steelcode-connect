<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'supplier_offer_prices')]
#[ORM\Index(name: 'idx_supplier_offer_prices_lookup', columns: ['supplier_offer_id', 'currency', 'minimum_quantity'])]
class SupplierOfferPrice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'prices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SupplierOffer $supplierOffer;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $minimumQuantity;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $unitCost;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil;

    public function __construct(
        SupplierOffer $supplierOffer,
        string $minimumQuantity,
        string $unitCost,
        string $currency,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
    ) {
        $this->id = Uuid::v7();
        $this->supplierOffer = $supplierOffer;
        $this->minimumQuantity = $minimumQuantity;
        $this->unitCost = $unitCost;
        $this->currency = $currency;
        $this->validFrom = $validFrom;
        $this->validUntil = $validUntil;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMinimumQuantity(): string
    {
        return $this->minimumQuantity;
    }

    public function getUnitCost(): string
    {
        return $this->unitCost;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function isValidOn(\DateTimeImmutable $date): bool
    {
        return ($this->validFrom === null || $this->validFrom <= $date)
            && ($this->validUntil === null || $this->validUntil >= $date);
    }
}
