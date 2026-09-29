<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_variant_option_group_properties')]
#[ORM\UniqueConstraint(name: 'uniq_variant_option_group_property', columns: ['option_group_id', 'property_id'])]
class ProductVariantOptionGroupProperty
{
    #[ORM\Id] #[ORM\Column(type:'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false, onDelete:'CASCADE')] private Tenant $tenant;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false, onDelete:'CASCADE')] private ProductVariantOptionGroup $optionGroup;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable:false, onDelete:'RESTRICT')] private Property $property;
    public function __construct(Tenant $tenant, ProductVariantOptionGroup $optionGroup, Property $property)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->optionGroup = $optionGroup;
        $this->property = $property;
    }
    public function getProperty(): Property
    {
        return $this->property;
    }
}
