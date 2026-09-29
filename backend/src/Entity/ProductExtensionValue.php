<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_extension_values')]
class ProductExtensionValue
{
    #[ORM\Id] #[ORM\Column(type: 'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Tenant $tenant;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Product $product;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?Locale $locale = null;
    #[ORM\Column(length: 128)] private string $namespace;
    #[ORM\Column(name: 'field_key', length: 255)] private string $key;
    #[ORM\Column(type: 'json')] private mixed $value;
    #[ORM\Column] private int $position;
    #[ORM\Column(length: 32)] private string $source;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    public function __construct(Tenant $tenant, Product $product, string $namespace, string $key, mixed $value, int $position = 0, string $source = 'manual', ?Locale $locale = null)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->product = $product;
        $this->namespace = $namespace;
        $this->key = $key;
        $this->value = $value;
        $this->position = $position;
        $this->source = $source;
        $this->locale = $locale;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function getNamespace(): string
    {
        return $this->namespace;
    }
    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getKey(): string
    {
        return $this->key;
    }
    public function getValue(): mixed
    {
        return $this->value;
    }
}
