<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_memberships')]
#[ORM\UniqueConstraint(name: 'uniq_membership_tenant_user', columns: ['tenant_id', 'user_id'])]
class TenantMembership
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 32)]
    private string $role;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, User $user, string $role)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->user = $user;
        $this->role = $role;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getRole(): string
    {
        return $this->role;
    }
}
