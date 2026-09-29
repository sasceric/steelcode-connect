<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_variant_option_groups')]
#[ORM\UniqueConstraint(name: 'uniq_variant_option_group', columns: ['product_id', 'property_group_id'])]
class ProductVariantOptionGroup
{
    #[ORM\Id] #[ORM\Column(type: 'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete:'CASCADE')] private Tenant $tenant;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete:'CASCADE')] private Product $product;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete:'RESTRICT')] private PropertyGroup $propertyGroup;
    #[ORM\Column] private int $position;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    public function __construct(Tenant $tenant, Product $product, PropertyGroup $propertyGroup, int $position)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->propertyGroup = $propertyGroup;
        $this->position = $position;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function getId(): Uuid
    {
        return $this->id;
    } public function getPropertyGroup(): PropertyGroup
    {
        return $this->propertyGroup;
    }
}
