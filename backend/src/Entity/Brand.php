<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'brands')]
#[ORM\Index(name: 'idx_brand_tenant', columns: ['tenant_id'])]
class Brand
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;
    /** Source metadata retains hierarchy, image and provider-specific fields. */
    #[ORM\Column(type: 'json')]
    private array $sourcePayload = [];
    public function __construct(Tenant $tenant)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function update(
        ?string $slug,
        array $sourcePayload,
    ): void
    {
        $this->slug = $slug;
        $this->sourcePayload = $sourcePayload;
    }
}
