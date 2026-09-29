<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'properties')]
#[ORM\UniqueConstraint(name: 'uniq_property_code', columns: ['property_group_id', 'code'])]
class Property
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PropertyGroup $propertyGroup;
    #[ORM\Column(length: 255)]
    private string $name;
    #[ORM\Column(length: 100)]
    private string $code;
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $colorHex;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $media = null;
    #[ORM\Column]
    private int $position;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, PropertyGroup $propertyGroup, string $name, string $code, ?string $colorHex, int $position)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->propertyGroup = $propertyGroup;
        $this->name = $name;
        $this->code = $code;
        $this->colorHex = $colorHex;
        $this->position = $position;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getPropertyGroup(): PropertyGroup
    {
        return $this->propertyGroup;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getCode(): string
    {
        return $this->code;
    }
    public function getColorHex(): ?string
    {
        return $this->colorHex;
    }
    public function getPosition(): int
    {
        return $this->position;
    }
    public function getMedia(): ?Media
    {
        return $this->media;
    }
    public function update(string $name, string $code, ?string $colorHex, int $position, ?Media $media = null): void
    {
        $this->name = $name;
        $this->code = $code;
        $this->colorHex = $colorHex;
        $this->position = $position;
        $this->media = $media;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
