<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_cross_selling_assignments')]
#[ORM\UniqueConstraint(
    name: 'uniq_cross_selling_assigned_product',
    columns: ['cross_selling_id', 'assigned_product_id'],
)]
class ProductCrossSellingAssignment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductCrossSelling $crossSelling;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $assignedProduct;

    #[ORM\Column]
    private int $position;

    public function __construct(
        ProductCrossSelling $crossSelling,
        Product $assignedProduct,
        int $position,
    ) {
        $this->id = Uuid::v7();
        $this->crossSelling = $crossSelling;
        $this->assignedProduct = $assignedProduct;
        $this->position = $position;
    }

    public function getAssignedProduct(): Product
    {
        return $this->assignedProduct;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function update(int $position): void
    {
        $this->position = $position;
    }
}
