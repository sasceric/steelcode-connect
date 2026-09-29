<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'categories')]
#[ORM\Index(name: 'idx_category_tenant_parent_position', columns: ['tenant_id', 'parent_id', 'position'])]
class Category
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $media = null;

    #[ORM\Column]
    private int $position;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private bool $visible = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, int $position)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->position = $position;
        $this->parent = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getParent(): ?self { return $this->parent; }
    public function getMedia(): ?Media { return $this->media; }
    public function getPosition(): int { return $this->position; }
    public function isActive(): bool { return $this->active; }
    public function isVisible(): bool { return $this->visible; }

    public function update(bool $active, bool $visible, ?Media $media): void
    {
        $this->active = $active;
        $this->visible = $visible;
        $this->media = $media;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function move(?self $parent, int $position): void
    {
        $this->parent = $parent;
        $this->position = $position;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
