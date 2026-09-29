<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'unit_translations')]
#[ORM\UniqueConstraint(name: 'uniq_unit_translation_locale', columns: ['unit_id', 'locale_id'])]
class UnitTranslation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Unit $unit;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Locale $locale;

    #[ORM\Column(length: 255)]
    private string $name;

    public function __construct(Unit $unit, Locale $locale, string $name)
    {
        $this->id = Uuid::v7();
        $this->unit = $unit;
        $this->locale = $locale;
        $this->name = $name;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }

    public function update(string $name): void
    {
        $this->name = $name;
    }
}
