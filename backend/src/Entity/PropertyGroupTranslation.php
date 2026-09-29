<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'property_group_translations')]
#[ORM\UniqueConstraint(name: 'uniq_property_group_translation_locale', columns: ['property_group_id', 'locale_id'])]
class PropertyGroupTranslation
{
    #[ORM\Id] #[ORM\Column(type: 'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private PropertyGroup $propertyGroup;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Locale $locale;
    #[ORM\Column(length: 255)] private string $name;
    public function __construct(PropertyGroup $propertyGroup, Locale $locale, string $name)
    {
        $this->id = Uuid::v7();
        $this->propertyGroup = $propertyGroup;
        $this->locale = $locale;
        $this->name = $name;
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function update(string $name): void
    {
        $this->name = $name;
    }
}
