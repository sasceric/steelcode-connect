<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_media')]
#[ORM\UniqueConstraint(
    name: 'uniq_product_media_position',
    columns: ['tenant_id', 'product_id', 'sort_order'],
)]
#[ORM\Index(
    name: 'idx_product_media_checksum',
    columns: ['tenant_id', 'checksum'],
)]
#[ORM\Index(
    name: 'idx_product_media_product_position',
    columns: ['product_id', 'sort_order'],
)]
class ProductMedia
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Media $media;

    #[ORM\Column(length: 512)]
    private string $storageKey;

    #[ORM\Column(length: 255)]
    private string $fileName;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $fileExtension;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize;

    #[ORM\Column(length: 127, nullable: true)]
    private ?string $mimeType;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $originalUrl = null;

    #[ORM\Column(length: 32)]
    private string $mediaType = 'image';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column]
    private int $sortOrder;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksum = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Tenant $tenant,
        Product $product,
        Media $media,
        int $sortOrder,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->media = $media;
        $this->copyMediaMetadata($media);
        $this->sortOrder = $sortOrder;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function update(?string $altText, int $sortOrder): void
    {
        $this->altText = $altText;
        $this->sortOrder = $sortOrder;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function replaceMedia(Media $media): void
    {
        $this->media = $media;
        $this->copyMediaMetadata($media);
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function copyMediaMetadata(Media $media): void
    {
        $this->storageKey = $media->getStorageKey();
        $this->fileName = $media->getFileName();
        $this->fileExtension = $media->getFileExtension();
        $this->fileSize = $media->getFileSize();
        $this->mimeType = $media->getMimeType();
        $this->checksum = $media->getChecksum();
    }
}
