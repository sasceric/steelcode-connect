<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'brand_translations')]
#[ORM\UniqueConstraint(name: 'uniq_brand_translation_locale', columns: ['brand_id', 'locale_id'])]
class BrandTranslation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Brand $brand;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Locale $locale;
    #[ORM\Column(length: 255)]
    private string $name;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;
    public function __construct(
        Brand $brand,
        Locale $locale,
        string $name,
    )
    {
        $this->id = Uuid::v7();
        $this->brand = $brand;
        $this->locale = $locale;
        $this->name = $name;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function update(
        string $name,
        ?string $description,
    ): void
    {
        $this->name = $name;
        $this->description = $description;
    }
}
