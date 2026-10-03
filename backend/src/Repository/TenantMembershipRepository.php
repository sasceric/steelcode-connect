<?php

namespace App\Repository;

use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TenantMembership> */
final class TenantMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantMembership::class);
    }

    /** No company-switching UI exists yet: never pick an arbitrary membership. */
    public function forUser(User $user): ?TenantMembership
    {
        $memberships = $this->findBy(['user' => $user], limit: 2);

        return count($memberships) === 1 ? $memberships[0] : null;
    }
}
