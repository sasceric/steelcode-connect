<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_variant_option_values')]
#[ORM\UniqueConstraint(name: 'uniq_variant_option_value', columns: ['product_id', 'property_id'])]
class ProductVariantOptionValue
{
    #[ORM\Id] #[ORM\Column(type:'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false, onDelete:'CASCADE')] private Tenant $tenant;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false, onDelete:'CASCADE')] private Product $product;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false, onDelete:'RESTRICT')] private Property $property;
    public function __construct(Tenant $tenant, Product $product, Property $property)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->property = $property;
    }
    public function getProperty(): Property
    {
        return $this->property;
    }
}
