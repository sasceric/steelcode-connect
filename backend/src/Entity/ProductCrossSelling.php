<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_cross_sellings')]
#[ORM\UniqueConstraint(
    name: 'uniq_product_cross_selling_source',
    columns: ['product_id', 'source_id'],
)]
class ProductCrossSelling
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

    #[ORM\Column(length: 64)]
    private string $sourceId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 32)]
    private string $type;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $position;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sourceProductStreamId = null;

    public function __construct(
        Tenant $tenant,
        Product $product,
        string $sourceId,
        string $name,
        string $type,
        bool $active,
        int $position,
        ?string $sourceProductStreamId,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->sourceId = $sourceId;
        $this->name = $name;
        $this->type = $type;
        $this->active = $active;
        $this->position = $position;
        $this->sourceProductStreamId = $sourceProductStreamId;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getSourceProductStreamId(): ?string
    {
        return $this->sourceProductStreamId;
    }

    public function update(
        string $name,
        string $type,
        bool $active,
        int $position,
        ?string $sourceProductStreamId,
    ): void {
        $this->name = $name;
        $this->type = $type;
        $this->active = $active;
        $this->position = $position;
        $this->sourceProductStreamId = $sourceProductStreamId;
    }
}
