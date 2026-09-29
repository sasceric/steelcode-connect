<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'integration_entity_mappings')]
#[ORM\UniqueConstraint(
    name: 'uniq_integration_entity_mapping_source',
    columns: ['connection_id', 'entity_type', 'external_id'],
)]
class IntegrationEntityMapping
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
    private string $entityType;

    #[ORM\Column(length: 64)]
    private string $externalId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $localId;

    public function __construct(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $entityType,
        string $externalId,
        Uuid $localId,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->connection = $connection;
        $this->entityType = $entityType;
        $this->externalId = $externalId;
        $this->localId = $localId;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getLocalId(): Uuid
    {
        return $this->localId;
    }

    public function remap(Uuid $localId): void
    {
        $this->localId = $localId;
    }
}
