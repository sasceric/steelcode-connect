<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'property_groups')]
#[ORM\UniqueConstraint(name: 'uniq_property_group_code', columns: ['tenant_id', 'code'])]
class PropertyGroup
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;
    #[ORM\Column(length: 255)]
    private string $name;
    #[ORM\Column(length: 100)]
    private string $code;
    #[ORM\Column(length: 16)]
    private string $displayType;
    #[ORM\Column]
    private bool $isFilterable;
    #[ORM\Column]
    private bool $displayOnProductDetail;
    #[ORM\Column(length: 16)]
    private string $sorting;
    #[ORM\Column]
    private int $position;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, string $name, string $code, string $displayType, bool $isFilterable, bool $displayOnProductDetail, string $sorting, int $position)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->name = $name;
        $this->code = $code;
        $this->displayType = $displayType;
        $this->isFilterable = $isFilterable;
        $this->displayOnProductDetail = $displayOnProductDetail;
        $this->sorting = $sorting;
        $this->position = $position;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getTenant(): Tenant
    {
        return $this->tenant;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getCode(): string
    {
        return $this->code;
    }
    public function getDisplayType(): string
    {
        return $this->displayType;
    }
    public function isFilterable(): bool
    {
        return $this->isFilterable;
    }
    public function isDisplayedOnProductDetail(): bool
    {
        return $this->displayOnProductDetail;
    }
    public function getSorting(): string
    {
        return $this->sorting;
    }
    public function getPosition(): int
    {
        return $this->position;
    }
    public function update(string $name, string $code, string $displayType, bool $isFilterable, bool $displayOnProductDetail, string $sorting, int $position): void
    {
        $this->name = $name;
        $this->code = $code;
        $this->displayType = $displayType;
        $this->isFilterable = $isFilterable;
        $this->displayOnProductDetail = $displayOnProductDetail;
        $this->sorting = $sorting;
        $this->position = $position;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
