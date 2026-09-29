<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tax_translations')]
#[ORM\UniqueConstraint(name: 'uniq_tax_translation_locale', columns: ['tax_id', 'locale_id'])]
class TaxTranslation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tax $tax;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Locale $locale;

    #[ORM\Column(length: 255)]
    private string $name;

    public function __construct(Tax $tax, Locale $locale, string $name)
    {
        $this->id = Uuid::v7();
        $this->tax = $tax;
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

    public function getTax(): Tax
    {
        return $this->tax;
    }

    public function update(string $name): void
    {
        $this->name = $name;
    }
}
