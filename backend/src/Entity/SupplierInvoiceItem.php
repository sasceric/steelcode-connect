<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'supplier_invoice_items')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_invoice_order_item', columns: ['invoice_id', 'purchase_order_item_id'])]
class SupplierInvoiceItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SupplierInvoice $invoice;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private PurchaseOrderItem $purchaseOrderItem;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantity;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $unitCost;

    public function __construct(SupplierInvoice $invoice, PurchaseOrderItem $orderItem, string $quantity, string $unitCost)
    {
        $this->id = Uuid::v7();
        $this->invoice = $invoice;
        $this->purchaseOrderItem = $orderItem;
        $this->quantity = $quantity;
        $this->unitCost = $unitCost;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function update(string $quantity, string $unitCost): void
    {
        $this->quantity = $quantity;
        $this->unitCost = $unitCost;
    }
    public function getPurchaseOrderItem(): PurchaseOrderItem
    {
        return $this->purchaseOrderItem;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getUnitCost(): string
    {
        return $this->unitCost;
    }
}
