<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_times')]
class DeliveryTime
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(type: 'json')]
    private array $labels;

    #[ORM\Column]
    private int $min;

    #[ORM\Column]
    private int $max;

    #[ORM\Column(length: 16)]
    private string $unit;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        Tenant $tenant,
        array $labels,
        int $min,
        int $max,
        string $unit,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->labels = $labels;
        $this->min = $min;
        $this->max = $max;
        $this->unit = $unit;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function update(array $labels, int $min, int $max, string $unit, bool $active): void
    {
        $this->labels = $labels;
        $this->min = $min;
        $this->max = $max;
        $this->unit = $unit;
        $this->active = $active;
    }
}
