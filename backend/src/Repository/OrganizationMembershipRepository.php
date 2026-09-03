<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationMembership>
 */
class OrganizationMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationMembership::class);
    }

    /**
     * The caller's membership in one organization, with the organization already loaded.
     *
     * One query answers three questions at once: does the organization exist, is the caller
     * a member of it, and with what authority. That is what lets the resolver treat "not
     * yours" and "not there" as the same answer.
     */
    public function findForUserAndOrganization(User $user, int $organizationId): ?OrganizationMembership
    {
        return $this->createQueryBuilder('m')
            ->addSelect('o')
            ->innerJoin('m.organization', 'o')
            ->where('m.user = :user')
            ->andWhere('o.id = :organizationId')
            ->setParameter('user', $user)
            ->setParameter('organizationId', $organizationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<OrganizationMembership>
     */
    public function findByOrganization(Organization $organization, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('u')
            ->innerJoin('m.user', 'u')
            ->where('m.organization = :organization')
            // Owners first, then admins, then members — CASE keeps the ordering meaningful
            // instead of alphabetical, where ADMIN would sort above OWNER.
            ->orderBy('CASE m.role WHEN \'OWNER\' THEN 0 WHEN \'ADMIN\' THEN 1 ELSE 2 END', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->setParameter('organization', $organization);

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        /* @var list<OrganizationMembership> */
        return $qb->getQuery()->getResult();
    }

    /**
     * One membership of one organization, addressed by its own id.
     *
     * The organization is part of the lookup, not checked afterwards: without it, a
     * membership id from another organization would resolve, and the caller's authority
     * over *their* organization would be applied to somebody else's row.
     */
    public function findOneInOrganization(Organization $organization, int $membershipId): ?OrganizationMembership
    {
        return $this->createQueryBuilder('m')
            ->addSelect('u')
            ->innerJoin('m.user', 'u')
            ->where('m.organization = :organization')
            ->andWhere('m.id = :id')
            ->setParameter('organization', $organization)
            ->setParameter('id', $membershipId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsFor(Organization $organization, User $user): bool
    {
        return null !== $this->findOneBy(['organization' => $organization, 'user' => $user]);
    }
}
