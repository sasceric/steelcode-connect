<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'custom_fields')]
#[ORM\UniqueConstraint(name: 'uniq_custom_field_name', columns: ['custom_field_set_id', 'technical_name'])]
class CustomField
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CustomFieldSet $customFieldSet = null;

    #[ORM\Column(length: 100)]
    private string $technicalName;

    #[ORM\Column(length: 32)]
    private string $type;

    #[ORM\Column(type: 'json')]
    private array $labels;

    #[ORM\Column(type: 'json')]
    private array $config;

    #[ORM\Column]
    private int $position;

    public function __construct(
        Tenant $tenant,
        ?CustomFieldSet $set,
        string $name,
        string $type,
        array $labels,
        array $config,
        int $position,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->customFieldSet = $set;
        $this->technicalName = $name;
        $this->type = $type;
        $this->labels = $labels;
        $this->config = $config;
        $this->position = $position;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getCustomFieldSet(): ?CustomFieldSet
    {
        return $this->customFieldSet;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function update(
        string $name,
        string $type,
        array $labels,
        array $config,
        int $position,
    ): void {
        $this->technicalName = $name;
        $this->type = $type;
        $this->labels = $labels;
        $this->config = $config;
        $this->position = $position;
    }
}
