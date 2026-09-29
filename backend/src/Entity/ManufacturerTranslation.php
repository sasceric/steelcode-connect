<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'manufacturer_translations')]
#[ORM\UniqueConstraint(name: 'uniq_manufacturer_translation_locale', columns: ['manufacturer_id', 'locale_id'])]
class ManufacturerTranslation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Manufacturer $manufacturer;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Locale $locale;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metaKeywords = null;

    #[ORM\Column(type: 'json')]
    private array $customFields = [];

    public function __construct(Manufacturer $manufacturer, Locale $locale, string $name)
    {
        $this->id = Uuid::v7();
        $this->manufacturer = $manufacturer;
        $this->locale = $locale;
        $this->name = $name;
    }

    public function getManufacturer(): Manufacturer
    {
        return $this->manufacturer;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    /** @return array<string, mixed> */
    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    public function update(
        string $name,
        ?string $description,
        ?string $metaTitle,
        ?string $metaDescription,
        ?string $metaKeywords,
        array $customFields,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->metaTitle = $metaTitle;
        $this->metaDescription = $metaDescription;
        $this->metaKeywords = $metaKeywords;
        $this->customFields = $customFields;
    }
}
