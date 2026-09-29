<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_option_values')]
class ProductOptionValue
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductOptionGroup $optionGroup;

    #[ORM\Column(length: 255)]
    private string $value;

    #[ORM\Column]
    private int $position;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, ProductOptionGroup $optionGroup, string $value, int $position)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->optionGroup = $optionGroup;
        $this->value = $value;
        $this->position = $position;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getOptionGroup(): ProductOptionGroup
    {
        return $this->optionGroup;
    }
    public function getValue(): string
    {
        return $this->value;
    }
    public function getPosition(): int
    {
        return $this->position;
    }
}
