<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrganizationRole;
use App\Entity\RosterEntry;
use App\Entity\Season;
use App\Entity\SeasonTeam;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeasonTeam>
 */
class SeasonTeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeasonTeam::class);
    }

    /**
     * The clubs registered for a season, each with its squad size.
     *
     * Two queries, whatever the number of clubs. Both halves matter:
     *
     * `addSelect('t')` stops the club itself being a lazy proxy that the response builder
     * wakes one row at a time. Less obviously, so does the separate count: calling
     * `$seasonTeam->getRosterEntries()->count()` on an uninitialised collection **loads the
     * whole collection**, so twelve clubs would be twelve extra queries — and every one of
     * them would return data nobody wanted, only to have it counted and thrown away.
     *
     * A test asserts that the query count does not change with the number of clubs, which is
     * a stronger claim than any particular number.
     *
     * @return list<array{seasonTeam: SeasonTeam, squadSize: int}>
     */
    public function findForSeason(Season $season): array
    {
        /** @var list<SeasonTeam> $seasonTeams */
        $seasonTeams = $this->createQueryBuilder('st')
            ->addSelect('t')
            ->innerJoin('st.team', 't')
            ->where('st.season = :season')
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('st.id', 'ASC')
            ->setParameter('season', $season)
            ->getQuery()
            ->getResult();

        if ([] === $seasonTeams) {
            return [];
        }

        $sizes = $this->squadSizesFor(array_map(
            static fn (SeasonTeam $seasonTeam): int => (int) $seasonTeam->getId(),
            $seasonTeams,
        ));

        return array_map(
            static fn (SeasonTeam $seasonTeam): array => [
                'seasonTeam' => $seasonTeam,
                'squadSize' => $sizes[(int) $seasonTeam->getId()] ?? 0,
            ],
            $seasonTeams,
        );
    }

    /**
     * @return array{0: SeasonTeam, role: OrganizationRole}|null
     */
    public function findScoped(User $user, int $organizationId, int $leagueId, int $seasonId, int $seasonTeamId): ?array
    {
        /* @var array{0: SeasonTeam, role: OrganizationRole}|null */
        return $this->createQueryBuilder('st')
            ->select('st', 't', 's', 'l', 'm.role AS role')
            ->innerJoin('st.team', 't')
            ->innerJoin('st.season', 's')
            ->innerJoin('s.league', 'l')
            ->innerJoin('l.organization', 'o')
            ->innerJoin('o.memberships', 'm', 'WITH', 'm.user = :user')
            ->where('st.id = :seasonTeamId')
            ->andWhere('s.id = :seasonId')
            ->andWhere('l.id = :leagueId')
            ->andWhere('o.id = :organizationId')
            ->setParameter('user', $user)
            ->setParameter('seasonTeamId', $seasonTeamId)
            ->setParameter('seasonId', $seasonId)
            ->setParameter('leagueId', $leagueId)
            ->setParameter('organizationId', $organizationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isRegistered(Season $season, Team $team): bool
    {
        return null !== $this->findOneBy(['season' => $season, 'team' => $team]);
    }

    /**
     * The clubs registered for a season, as `team id => name`.
     *
     * Only the two columns the table needs. `findForSeason` hydrates entities and their squad
     * counts, which is the right shape for the clubs page and the wrong one for a league
     * table that will discard everything but the name.
     *
     * @return array<int, string>
     */
    public function namesForSeason(Season $season): array
    {
        /** @var list<array{id: int|string, name: string}> $rows */
        $rows = $this->createQueryBuilder('st')
            ->select('t.id AS id', 't.name AS name')
            ->innerJoin('st.team', 't')
            ->where('st.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getArrayResult();

        $names = [];

        foreach ($rows as $row) {
            $names[(int) $row['id']] = $row['name'];
        }

        return $names;
    }

    /**
     * How many players are registered for each of these squads.
     *
     * Deliberately not `$seasonTeam->getRosterEntries()->count()`: on an uninitialised
     * collection that loads the whole collection, so counting a page of clubs that way is a
     * query per club which fetches rows nobody wanted only to throw them away.
     *
     * Grouped separately rather than as an aggregate beside an entity select: mixing COUNT()
     * with hydrated entities needs a GROUP BY over every selected column, which is where
     * ONLY_FULL_GROUP_BY differences between databases start to bite.
     *
     * @param list<int> $seasonTeamIds
     *
     * @return array<int, int> season team id => squad size
     */
    public function squadSizesFor(array $seasonTeamIds): array
    {
        if ([] === $seasonTeamIds) {
            return [];
        }

        /** @var list<array{seasonTeamId: int|string, squadSize: int|string}> $counts */
        $counts = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(r.seasonTeam) AS seasonTeamId', 'COUNT(r.id) AS squadSize')
            ->from(RosterEntry::class, 'r')
            ->where('IDENTITY(r.seasonTeam) IN (:seasonTeams)')
            ->groupBy('r.seasonTeam')
            ->setParameter('seasonTeams', $seasonTeamIds)
            ->getQuery()
            ->getResult();

        $sizes = [];

        foreach ($counts as $row) {
            // COUNT comes back from PostgreSQL as bigint, which the driver hands over as text.
            $sizes[(int) $row['seasonTeamId']] = (int) $row['squadSize'];
        }

        return $sizes;
    }

    /**
     * Every season a page of clubs has been registered for, newest first.
     *
     * One query answers both of the questions a club row asks — how many seasons it has
     * played (the row count) and which one is current (the first row) — because the rows
     * arrive together anyway.
     *
     * The season and its league are fetch-joined: the caller turns them into a link, and a
     * lazy proxy there would be two queries per club.
     *
     * @param list<int> $teamIds
     *
     * @return array<int, list<SeasonTeam>> team id => registrations, newest season first
     */
    public function registrationsForTeams(array $teamIds): array
    {
        if ([] === $teamIds) {
            return [];
        }

        /** @var list<SeasonTeam> $rows */
        $rows = $this->createQueryBuilder('st')
            ->addSelect('s', 'l')
            ->innerJoin('st.season', 's')
            ->innerJoin('s.league', 'l')
            ->where('IDENTITY(st.team) IN (:teams)')
            ->orderBy('s.startDate', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->addOrderBy('st.id', 'DESC')
            ->setParameter('teams', $teamIds)
            ->getQuery()
            ->getResult();

        $grouped = [];

        foreach ($rows as $registration) {
            // Free on an unloaded proxy — Doctrine keeps the identifier without a round trip.
            $grouped[(int) $registration->getTeam()->getId()][] = $registration;
        }

        return $grouped;
    }
}
