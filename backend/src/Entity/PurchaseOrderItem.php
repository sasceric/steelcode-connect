<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'purchase_order_items')]
#[ORM\UniqueConstraint(name: 'uniq_purchase_order_product', columns: ['purchase_order_id', 'product_id'])]
class PurchaseOrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PurchaseOrder $purchaseOrder;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Product $product;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantity;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $receivedQuantity = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $damagedQuantity = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $unitCost;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $supplierSku;

    #[ORM\Column(length: 64)]
    private string $purchaseUnit = 'unit';

    #[ORM\Column]
    private int $stockUnitsPerPurchaseUnit = 1;

    public function __construct(
        PurchaseOrder $purchaseOrder,
        Product $product,
        string $quantity,
        string $unitCost,
        ?string $supplierSku,
        string $purchaseUnit = 'unit',
        int $stockUnitsPerPurchaseUnit = 1,
    )
    {
        $this->id = Uuid::v7();
        $this->purchaseOrder = $purchaseOrder;
        $this->product = $product;
        $this->quantity = $quantity;
        $this->unitCost = $unitCost;
        $this->supplierSku = $supplierSku;
        $this->purchaseUnit = $purchaseUnit;
        $this->stockUnitsPerPurchaseUnit = $stockUnitsPerPurchaseUnit;
    }

    public function receive(string $good, string $damaged): void
    {
        $this->receivedQuantity = number_format((float) $this->receivedQuantity + (float) $good, 4, '.', '');
        $this->damagedQuantity = number_format((float) $this->damagedQuantity + (float) $damaged, 4, '.', '');
    }

    public function getOpenQuantity(): string
    {
        return number_format((float) $this->quantity - (float) $this->receivedQuantity - (float) $this->damagedQuantity, 4, '.', '');
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getReceivedQuantity(): string
    {
        return $this->receivedQuantity;
    }

    public function getDamagedQuantity(): string
    {
        return $this->damagedQuantity;
    }

    public function getUnitCost(): string
    {
        return $this->unitCost;
    }

    public function getSupplierSku(): ?string
    {
        return $this->supplierSku;
    }

    public function getPurchaseUnit(): string
    {
        return $this->purchaseUnit;
    }

    public function getStockUnitsPerPurchaseUnit(): int
    {
        return $this->stockUnitsPerPurchaseUnit;
    }

    public function toStockQuantity(string $purchaseQuantity): string
    {
        return number_format((float) $purchaseQuantity * $this->stockUnitsPerPurchaseUnit, 4, '.', '');
    }
}
