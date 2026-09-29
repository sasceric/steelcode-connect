<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'media')]
class Media
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

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

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksum;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Tenant $tenant,
        string $storageKey,
        string $fileName,
        ?string $fileExtension,
        ?int $fileSize,
        ?string $mimeType,
        ?string $checksum,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->storageKey = $storageKey;
        $this->fileName = $fileName;
        $this->fileExtension = $fileExtension;
        $this->fileSize = $fileSize;
        $this->mimeType = $mimeType;
        $this->checksum = $checksum;
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

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }
}
