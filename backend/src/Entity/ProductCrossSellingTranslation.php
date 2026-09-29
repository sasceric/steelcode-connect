<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_cross_selling_translations')]
#[ORM\UniqueConstraint(
    name: 'uniq_product_cross_selling_translation_locale',
    columns: ['cross_selling_id', 'locale_id'],
)]
class ProductCrossSellingTranslation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductCrossSelling $crossSelling;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Locale $locale;

    #[ORM\Column(length: 255)]
    private string $name;

    public function __construct(
        ProductCrossSelling $crossSelling,
        Locale $locale,
        string $name,
    ) {
        $this->id = Uuid::v7();
        $this->crossSelling = $crossSelling;
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

    public function update(string $name): void
    {
        $this->name = $name;
    }
}
