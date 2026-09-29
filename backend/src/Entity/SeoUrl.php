<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'seo_urls')]
#[ORM\Index(
    name: 'idx_seo_url_entity',
    columns: ['tenant_id', 'entity_type', 'entity_id'],
)]
class SeoUrl
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Locale $locale;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?IntegrationSalesChannel $salesChannel;

    #[ORM\Column(length: 64)]
    private string $entityType;

    #[ORM\Column(type: 'uuid')]
    private Uuid $entityId;

    #[ORM\Column(length: 1024)]
    private string $path;

    #[ORM\Column]
    private bool $canonical = true;

    #[ORM\Column]
    private bool $modified = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(nullable: true)]
    private ?int $redirectCode = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $redirectTarget = null;

    #[ORM\Column(length: 16)]
    private string $source;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Tenant $tenant,
        Locale $locale,
        string $entityType,
        Uuid $entityId,
        string $path,
        bool $modified,
        string $source,
        ?IntegrationSalesChannel $salesChannel = null,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->locale = $locale;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->path = $path;
        $this->modified = $modified;
        $this->source = $source;
        $this->salesChannel = $salesChannel;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getSalesChannel(): ?IntegrationSalesChannel
    {
        return $this->salesChannel;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): Uuid
    {
        return $this->entityId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function isCanonical(): bool
    {
        return $this->canonical;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function redirectTo(self $target, int $redirectCode = 301): void
    {
        $this->canonical = false;
        $this->redirectCode = $redirectCode;
        $this->redirectTarget = $target;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateCanonicalMetadata(bool $modified, string $source): void
    {
        $this->modified = $modified;
        $this->source = $source;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
