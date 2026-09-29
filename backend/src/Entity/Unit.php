<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'units')]
#[ORM\UniqueConstraint(name: 'uniq_unit_tenant_code', columns: ['tenant_id', 'code'])]
class Unit
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 64)]
    private string $symbol;

    #[ORM\Column(type: 'json')]
    private array $labels;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        Tenant $tenant,
        string $code,
        string $symbol,
        array $labels,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->code = $code;
        $this->symbol = $symbol;
        $this->labels = $labels;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function update(string $code, string $symbol, array $labels, bool $active): void
    {
        $this->code = $code;
        $this->symbol = $symbol;
        $this->labels = $labels;
        $this->active = $active;
    }
}
