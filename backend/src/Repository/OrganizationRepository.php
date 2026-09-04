<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * Every organization the user belongs to, newest membership last.
     *
     * The membership join is not a filter that a caller could forget to apply — it is the
     * only way this method knows how to build the query.
     *
     * @return list<array{organization: Organization, role: OrganizationRole, memberCount: int}>
     */
    public function findForUser(User $user, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->select('o AS organization', 'm.role AS role', 'COUNT(all_members.id) AS memberCount')
            ->innerJoin('o.memberships', 'm', 'WITH', 'm.user = :user')
            ->leftJoin('o.memberships', 'all_members')
            ->groupBy('o.id')
            ->addGroupBy('m.role')
            ->orderBy('o.name', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->setParameter('user', $user);

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('LOWER(o.name) LIKE :search OR LOWER(o.slug) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower(trim($search)).'%');
        }

        // Doctrine applies the column's enumType even to a scalar select, so `role` arrives
        // as an OrganizationRole rather than as its backing string.
        /** @var list<array{organization: Organization, role: OrganizationRole, memberCount: int|string}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(
            static fn (array $row): array => [
                'organization' => $row['organization'],
                'role' => $row['role'],
                'memberCount' => (int) $row['memberCount'],
            ],
            $rows,
        );
    }

    /**
     * True when the slug is already taken. Used to pick a free one, not to validate input:
     * the unique index is what actually guarantees it.
     */
    public function slugExists(string $slug): bool
    {
        return null !== $this->findOneBy(['slug' => $slug]);
    }
}
