<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_properties')]
#[ORM\UniqueConstraint(name: 'uniq_product_property_key', columns: ['tenant_id', 'product_id', 'property_key'])]
class ProductProperty
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

    #[ORM\Column(length: 100)]
    private string $propertyKey;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(type: 'json')]
    private array $value;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(length: 16)]
    private string $source = 'manual';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @param array<mixed> $value */
    public function __construct(Tenant $tenant, Product $product, string $propertyKey, string $label, array $value)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->propertyKey = $propertyKey;
        $this->label = $label;
        $this->value = $value;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getPropertyKey(): string
    {
        return $this->propertyKey;
    }
    public function getValue(): array
    {
        return $this->value;
    }
}
