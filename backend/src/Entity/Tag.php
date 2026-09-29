<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tags')]
#[ORM\UniqueConstraint(name: 'uniq_tag_tenant_name', columns: ['tenant_id', 'name'])]
class Tag
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;
    #[ORM\Column(length: 100)]
    private string $name;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    public function __construct(Tenant $tenant, string $name)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): Uuid
    {
        return $this->id;
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
