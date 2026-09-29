<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'inventory_count_items')]
class InventoryCountItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InventoryCount $count;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Product $product;
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $expectedQuantity;
    #[ORM\Column(nullable: true)]
    private ?int $expectedVersion;
    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $countedQuantity;
    public function __construct(
        InventoryCount $count,
        Product $product,
        string $expectedQuantity,
        ?int $expectedVersion,
        string $countedQuantity,
    ) {
        $this->id = Uuid::v7();
        $this->count = $count;
        $this->product = $product;
        $this->expectedQuantity = $expectedQuantity;
        $this->expectedVersion = $expectedVersion;
        $this->countedQuantity = $countedQuantity;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getExpectedQuantity(): string
    {
        return $this->expectedQuantity;
    }

    public function getExpectedVersion(): ?int
    {
        return $this->expectedVersion;
    }

    public function getCountedQuantity(): string
    {
        return $this->countedQuantity;
    }
}
