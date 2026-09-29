<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'category_translations')]
#[ORM\UniqueConstraint(name: 'uniq_category_translation_locale', columns: ['category_id', 'locale_id'])]
class CategoryTranslation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Category $category;

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

    public function __construct(Category $category, Locale $locale, string $name)
    {
        $this->id = Uuid::v7();
        $this->category = $category;
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
