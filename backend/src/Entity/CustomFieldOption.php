<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'custom_field_options')]
#[ORM\UniqueConstraint(name: 'uniq_custom_field_option_value', columns: ['custom_field_id', 'technical_value'])]
class CustomFieldOption
{
    #[ORM\Id] #[ORM\Column(type: 'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private CustomField $customField;
    #[ORM\Column(length: 100)] private string $technicalValue;
    #[ORM\Column(type: 'json')] private array $labels;
    #[ORM\Column] private int $position;
    public function __construct(CustomField $field, string $value, array $labels, int $position)
    {
        $this->id = Uuid::v7();
        $this->customField = $field;
        $this->technicalValue = $value;
        $this->labels = $labels;
        $this->position = $position;
    }
    public function getId(): Uuid
    {
        return $this->id;
    } public function getTechnicalValue(): string
    {
        return $this->technicalValue;
    } public function getLabels(): array
    {
        return $this->labels;
    } public function getPosition(): int
    {
        return $this->position;
    }
    public function update(string $value, array $labels, int $position): void
    {
        $this->technicalValue = $value;
        $this->labels = $labels;
        $this->position = $position;
    }
}
