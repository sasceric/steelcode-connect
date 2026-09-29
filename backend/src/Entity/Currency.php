<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'currencies')]
#[ORM\UniqueConstraint(name: 'uniq_currency_code', columns: ['code'])]
class Currency
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 3)]
    private string $code;

    #[ORM\Column(length: 32)]
    private string $symbol;

    #[ORM\Column]
    private int $decimalPrecision;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $code, string $symbol, int $decimalPrecision)
    {
        $this->id = Uuid::v7();
        $this->code = strtoupper($code);
        $this->symbol = $symbol;
        $this->decimalPrecision = $decimalPrecision;
        $this->createdAt = new \DateTimeImmutable();
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
    public function getDecimalPrecision(): int
    {
        return $this->decimalPrecision;
    }
}
