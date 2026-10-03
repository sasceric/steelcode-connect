<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_brands')]
#[ORM\UniqueConstraint(name: 'uniq_product_brand_source', columns: [
    'tenant_id',
    'product_id',
    'brand_id',
    'source_key',
])]
#[ORM\Index(name: 'idx_product_brand_lookup', columns: ['tenant_id', 'product_id'])]
class ProductBrand
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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Brand $brand;
    #[ORM\Column(length: 64)]
    private string $sourceKey;
    public function __construct(
        Product $product,
        Brand $brand,
        string $sourceKey = 'manual',
    )
    {
        if (!$product->getTenant()->getId()->equals($brand->getTenant()->getId())) {
            throw new \DomainException('Product and brand must belong to the same tenant.');
        }
        $this->id = Uuid::v7();
        $this->tenant = $product->getTenant();
        $this->product = $product;
        $this->brand = $brand;
        $this->sourceKey = $sourceKey;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function getSourceKey(): string
    {
        return $this->sourceKey;
    }
}
