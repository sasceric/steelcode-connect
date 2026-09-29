<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'property_translations')]
#[ORM\UniqueConstraint(name: 'uniq_property_translation_locale', columns: ['property_id', 'locale_id'])]
class PropertyTranslation
{
    #[ORM\Id] #[ORM\Column(type: 'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Property $property;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Locale $locale;
    #[ORM\Column(length: 255)] private string $name;
    public function __construct(Property $property, Locale $locale, string $name)
    {
        $this->id = Uuid::v7();
        $this->property = $property;
        $this->locale = $locale;
        $this->name = $name;
    }
    public function getProperty(): Property
    {
        return $this->property;
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
