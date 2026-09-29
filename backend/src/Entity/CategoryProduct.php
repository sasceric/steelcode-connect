<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'category_products')]
class CategoryProduct
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Category $category;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column]
    private int $position;

    public function __construct(Category $category, Product $product, int $position)
    {
        $this->category = $category;
        $this->product = $product;
        $this->position = $position;
    }

    public function getProduct(): Product { return $this->product; }

    public function getCategory(): Category { return $this->category; }
}
