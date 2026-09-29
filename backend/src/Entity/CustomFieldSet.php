<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'custom_field_sets')]
#[ORM\UniqueConstraint(name: 'uniq_custom_field_set_name', columns: ['tenant_id', 'technical_name'])]
class CustomFieldSet
{
    #[ORM\Id] #[ORM\Column(type: 'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Tenant $tenant;
    #[ORM\Column(length: 100)] private string $technicalName;
    #[ORM\Column(type: 'json')] private array $labels;
    #[ORM\Column(type: 'json')] private array $relations;
    #[ORM\Column] private int $position;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    public function __construct(Tenant $tenant, string $technicalName, array $labels, array $relations, int $position)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->technicalName = $technicalName;
        $this->labels = $labels;
        $this->relations = $relations;
        $this->position = $position;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function getId(): Uuid
    {
        return $this->id;
    } public function getTenant(): Tenant
    {
        return $this->tenant;
    } public function getTechnicalName(): string
    {
        return $this->technicalName;
    } public function getLabels(): array
    {
        return $this->labels;
    } public function getRelations(): array
    {
        return $this->relations;
    } public function getPosition(): int
    {
        return $this->position;
    }
    public function update(string $technicalName, array $labels, array $relations, int $position): void
    {
        $this->technicalName = $technicalName;
        $this->labels = $labels;
        $this->relations = $relations;
        $this->position = $position;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
