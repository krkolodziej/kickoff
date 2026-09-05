<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\League;
use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\OrganizationRole;
use App\Entity\Player;
use App\Entity\Team;
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

    /**
     * League, club and player counts for a set of organizations.
     *
     * Three grouped queries rather than one, and deliberately so. `findForUser()` already
     * LEFT JOINs memberships to count them; three more collection joins beside it would
     * multiply against each other into a cartesian product, and `COUNT(DISTINCT ...)` would
     * only hide that cost rather than remove it. DQL has no UNION, so three passes is the
     * honest shape — and this endpoint returns the handful of organizations one person
     * belongs to, not a page of thousands.
     *
     * @param list<int> $organizationIds
     *
     * @return array<int, array{leagues: int, teams: int, players: int}>
     */
    public function countsFor(array $organizationIds): array
    {
        if ([] === $organizationIds) {
            return [];
        }

        $counts = [];

        foreach ($organizationIds as $id) {
            // Seeded with zeros so that an empty organization answers "0 leagues" rather
            // than making every caller decide what a missing key means.
            $counts[$id] = ['leagues' => 0, 'teams' => 0, 'players' => 0];
        }

        foreach ([League::class => 'leagues', Team::class => 'teams', Player::class => 'players'] as $entity => $key) {
            foreach ($this->countGrouped($entity, $organizationIds) as $organizationId => $total) {
                if (isset($counts[$organizationId])) {
                    $counts[$organizationId][$key] = $total;
                }
            }
        }

        return $counts;
    }

    /**
     * @param class-string     $entity
     * @param list<int>        $organizationIds
     *
     * @return array<int, int>
     */
    private function countGrouped(string $entity, array $organizationIds): array
    {
        /** @var list<array{organizationId: int|string, total: int|string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(e.organization) AS organizationId', 'COUNT(e.id) AS total')
            ->from($entity, 'e')
            ->where('IDENTITY(e.organization) IN (:organizations)')
            ->groupBy('e.organization')
            ->setParameter('organizations', $organizationIds)
            ->getQuery()
            ->getResult();

        $totals = [];

        foreach ($rows as $row) {
            // COUNT arrives from PostgreSQL as bigint, which the driver hands over as text.
            $totals[(int) $row['organizationId']] = (int) $row['total'];
        }

        return $totals;
    }

    /**
     * How many people belong to one organization.
     *
     * A grouped COUNT rather than `$organization->getMemberships()->count()`, which on an
     * uninitialised collection loads every membership row in order to count it.
     */
    public function memberCountFor(int $organizationId): int
    {
        $count = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(OrganizationMembership::class, 'm')
            ->where('IDENTITY(m.organization) = :organization')
            ->setParameter('organization', $organizationId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
