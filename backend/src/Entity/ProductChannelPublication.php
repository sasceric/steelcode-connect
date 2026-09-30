<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_channel_publications')]
#[ORM\UniqueConstraint(
    name: 'uniq_product_channel_publication',
    columns: ['product_id', 'sales_channel_id'],
)]
class ProductChannelPublication
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
    #[ORM\JoinColumn(name: 'sales_channel_id', nullable: false, onDelete: 'CASCADE')]
    private IntegrationSalesChannel $salesChannel;

    #[ORM\Column]
    private int $visibility;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $externalProductId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Tenant $tenant,
        Product $product,
        IntegrationSalesChannel $salesChannel,
        int $visibility,
        ?string $externalProductId = null,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->salesChannel = $salesChannel;
        $this->visibility = $visibility;
        $this->externalProductId = $externalProductId;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSalesChannel(): IntegrationSalesChannel
    {
        return $this->salesChannel;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getVisibility(): int
    {
        return $this->visibility;
    }

    public function getExternalProductId(): ?string
    {
        return $this->externalProductId;
    }

    public function update(int $visibility, ?string $externalProductId = null): void
    {
        $this->visibility = $visibility;
        $this->externalProductId = $externalProductId;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
