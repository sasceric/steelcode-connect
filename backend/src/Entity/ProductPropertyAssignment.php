<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_property_assignments')]
#[ORM\UniqueConstraint(name: 'uniq_product_property_assignment', columns: ['product_id', 'property_id'])]
class ProductPropertyAssignment
{
    use CatalogueAssignmentSources;

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
    private Property $property;
    #[ORM\Column(length: 32)]
    private string $source;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, Product $product, Property $property, string $source = 'manual')
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->property = $property;
        $this->source = $source;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getProperty(): Property
    {
        return $this->property;
    }
    public function getSource(): string
    {
        return $this->source;
    }
}
