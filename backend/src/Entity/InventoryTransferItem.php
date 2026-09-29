<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'inventory_transfer_items')]
class InventoryTransferItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InventoryTransfer $transfer;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Product $product;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantity;

    public function __construct(InventoryTransfer $transfer, Product $product, string $quantity)
    {
        $this->id = Uuid::v7();
        $this->transfer = $transfer;
        $this->product = $product;
        $this->quantity = $quantity;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }
}
