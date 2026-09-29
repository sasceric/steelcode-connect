<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'integration_sales_channels')]
#[ORM\UniqueConstraint(
    name: 'uniq_integration_sales_channel_external',
    columns: ['connection_id', 'external_id'],
)]
class IntegrationSalesChannel
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IntegrationConnection $connection;

    #[ORM\Column(length: 64)]
    private string $externalId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $type = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: 'json')]
    private array $sourceData = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $externalId,
        string $name,
        ?string $type,
        bool $active,
        array $sourceData,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->connection = $connection;
        $this->externalId = $externalId;
        $this->name = $name;
        $this->type = $type;
        $this->active = $active;
        $this->sourceData = $sourceData;
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

    public function getConnection(): IntegrationConnection
    {
        return $this->connection;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getSourceData(): array
    {
        return $this->sourceData;
    }

    public function update(
        string $name,
        ?string $type,
        bool $active,
        array $sourceData,
    ): void {
        $this->name = $name;
        $this->type = $type;
        $this->active = $active;
        $this->sourceData = $sourceData;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
