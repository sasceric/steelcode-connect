<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_downloads')]
#[ORM\UniqueConstraint(
    name: 'uniq_product_download_media',
    columns: ['tenant_id', 'product_id', 'media_id'],
)]
class ProductDownload
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
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Media $media;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column]
    private int $position;

    public function __construct(
        Tenant $tenant,
        Product $product,
        Media $media,
        ?string $title,
        int $position,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->media = $media;
        $this->title = $title;
        $this->position = $position;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function update(Media $media, ?string $title, int $position): void
    {
        $this->media = $media;
        $this->title = $title;
        $this->position = $position;
    }
}
