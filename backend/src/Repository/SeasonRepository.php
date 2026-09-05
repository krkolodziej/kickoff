<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Input\ListQuery;
use App\Entity\League;
use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\Season;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Season>
 */
class SeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Season::class);
    }

    public function scopedQuery(League $league, ListQuery $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.league = :league')
            ->setParameter('league', $league);

        if (null !== $term = $query->searchTerm()) {
            $qb->andWhere('LOWER(s.name) LIKE :search')->setParameter('search', '%'.mb_strtolower($term).'%');
        }

        return $qb;
    }

    /**
     * The season, its league, its organization and the caller's role — one query.
     *
     * The chain is checked, not assumed: season 5 reached through league 9 is a missing row
     * when it actually belongs to league 3, so the nesting in the URL means something.
     *
     * @return array{0: Season, role: OrganizationRole}|null
     */
    public function findScoped(User $user, int $organizationId, int $leagueId, int $seasonId): ?array
    {
        /* @var array{0: Season, role: OrganizationRole}|null */
        return $this->createQueryBuilder('s')
            ->select('s', 'l', 'm.role AS role')
            ->innerJoin('s.league', 'l')
            ->innerJoin('l.organization', 'o')
            ->innerJoin('o.memberships', 'm', 'WITH', 'm.user = :user')
            ->where('s.id = :seasonId')
            ->andWhere('l.id = :leagueId')
            ->andWhere('o.id = :organizationId')
            ->setParameter('user', $user)
            ->setParameter('seasonId', $seasonId)
            ->setParameter('leagueId', $leagueId)
            ->setParameter('organizationId', $organizationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function nameExists(League $league, string $name, ?int $exceptId = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.league = :league')
            ->andWhere('s.name = :name')
            ->setParameter('league', $league)
            ->setParameter('name', $name);

        if (null !== $exceptId) {
            $qb->andWhere('s.id <> :exceptId')->setParameter('exceptId', $exceptId);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }

    /**
     * The season a visitor should be dropped into: the most recent one this organization runs.
     *
     * `addSelect('l')` because the caller needs the league's id to build a path, and a lazy
     * proxy there would be a second query for one integer.
     */
    public function latestForOrganization(Organization $organization): ?Season
    {
        /* @var Season|null */
        return $this->createQueryBuilder('s')
            ->addSelect('l')
            ->innerJoin('s.league', 'l')
            ->where('l.organization = :organization')
            ->orderBy('s.startDate', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The seasons belonging to a page of leagues, newest first, grouped by league.
     *
     * One query answers two questions — how many seasons a league has (the row count) and
     * which one is current (the first row) — because they arrive together anyway. Asking
     * separately would be two round trips for one fold.
     *
     * @param list<int> $leagueIds
     *
     * @return array<int, list<Season>> league id => seasons, newest first
     */
    public function forLeagues(array $leagueIds): array
    {
        if ([] === $leagueIds) {
            return [];
        }

        /** @var list<Season> $rows */
        $rows = $this->createQueryBuilder('s')
            ->where('IDENTITY(s.league) IN (:leagues)')
            ->orderBy('s.startDate', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setParameter('leagues', $leagueIds)
            ->getQuery()
            ->getResult();

        $grouped = [];

        foreach ($rows as $season) {
            // Reading the id off an unloaded league proxy costs nothing: Doctrine keeps the
            // identifier on the proxy, so this does not wake one league per season.
            $grouped[(int) $season->getLeague()->getId()][] = $season;
        }

        return $grouped;
    }
}
