<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Standings\SideAggregate;
use App\Entity\Fixture;
use App\Entity\MatchStatus;
use App\Entity\OrganizationRole;
use App\Entity\Season;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fixture>
 */
class FixtureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fixture::class);
    }

    /**
     * A season's calendar, with both clubs loaded.
     *
     * `addSelect` on both sides, because a 132-fixture list that lazy-loads two clubs per row
     * is 265 queries — the textbook N+1, and the reason the query-count test from the last
     * stage exists.
     *
     * @return list<Fixture>
     */
    /**
     * @param list<MatchStatus> $statuses
     *
     * @return list<Fixture>
     */
    public function findForSeason(
        Season $season,
        ?int $round = null,
        ?int $teamId = null,
        array $statuses = [],
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->addSelect('home', 'away')
            ->innerJoin('f.homeTeam', 'home')
            ->innerJoin('f.awayTeam', 'away')
            ->where('f.season = :season')
            ->orderBy('f.roundNumber', 'ASC')
            ->addOrderBy('f.leg', 'ASC')
            ->addOrderBy('home.name', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->setParameter('season', $season);

        if (null !== $round) {
            $qb->andWhere('f.roundNumber = :round')->setParameter('round', $round);
        }

        if (null !== $teamId) {
            // Home *or* away: "show me Resovia's season" means both.
            $qb->andWhere('home.id = :teamId OR away.id = :teamId')->setParameter('teamId', $teamId);
        }

        if ([] !== $statuses) {
            $qb->andWhere('f.status IN (:statuses)')->setParameter('statuses', $statuses);
        }

        /* @var list<Fixture> */
        return $qb->getQuery()->getResult();
    }

    /**
     * The fixture, its season, its league and the caller's role — one query, as everywhere.
     *
     * @return array{0: Fixture, role: OrganizationRole}|null
     */
    public function findScoped(
        User $user,
        int $organizationId,
        int $leagueId,
        int $seasonId,
        int $fixtureId,
    ): ?array {
        /* @var array{0: Fixture, role: OrganizationRole}|null */
        return $this->createQueryBuilder('f')
            ->select('f', 'home', 'away', 's', 'l', 'm.role AS role')
            ->innerJoin('f.homeTeam', 'home')
            ->innerJoin('f.awayTeam', 'away')
            ->innerJoin('f.season', 's')
            ->innerJoin('s.league', 'l')
            ->innerJoin('l.organization', 'o')
            ->innerJoin('o.memberships', 'm', 'WITH', 'm.user = :user')
            ->where('f.id = :fixtureId')
            ->andWhere('s.id = :seasonId')
            ->andWhere('l.id = :leagueId')
            ->andWhere('o.id = :organizationId')
            ->setParameter('user', $user)
            ->setParameter('fixtureId', $fixtureId)
            ->setParameter('seasonId', $seasonId)
            ->setParameter('leagueId', $leagueId)
            ->setParameter('organizationId', $organizationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function seasonHasFixtures(Season $season): bool
    {
        $count = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }

    public function deleteForSeason(Season $season): int
    {
        return (int) $this->createQueryBuilder('f')
            ->delete()
            ->where('f.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->execute();
    }

    /**
     * Every club's results in a season, as two rows per club.
     *
     * A fixture stores one club in `home_team_id` and the other in `away_team_id`, so a single
     * GROUP BY can only ever see a club from one side of the pitch. There is no way to ask for
     * "this club's season" in one grouped query without a UNION, which DQL does not have, so
     * the season arrives as a home-side pass and an away-side pass and is added up in PHP.
     *
     * Only FINISHED counts. Points are awarded at full time, not while a match is running.
     *
     * @return list<SideAggregate>
     */
    public function seasonAggregates(Season $season): array
    {
        return [
            ...$this->aggregateOneSide($season, home: true),
            ...$this->aggregateOneSide($season, home: false),
        ];
    }

    /**
     * @return list<SideAggregate>
     */
    private function aggregateOneSide(Season $season, bool $home): array
    {
        $side = $home ? 'homeTeam' : 'awayTeam';
        $mine = $home ? 'f.homeScore' : 'f.awayScore';
        $theirs = $home ? 'f.awayScore' : 'f.homeScore';

        /** @var list<array{teamId: int|string, played: int|string, won: int|string, drawn: int|string, lost: int|string, goalsFor: int|string|null, goalsAgainst: int|string|null}> $rows */
        $rows = $this->createQueryBuilder('f')
            ->select(\sprintf('IDENTITY(f.%s) AS teamId', $side))
            ->addSelect('COUNT(f.id) AS played')
            ->addSelect(\sprintf('SUM(CASE WHEN %s > %s THEN 1 ELSE 0 END) AS won', $mine, $theirs))
            ->addSelect(\sprintf('SUM(CASE WHEN %s = %s THEN 1 ELSE 0 END) AS drawn', $mine, $theirs))
            ->addSelect(\sprintf('SUM(CASE WHEN %s < %s THEN 1 ELSE 0 END) AS lost', $mine, $theirs))
            ->addSelect(\sprintf('SUM(%s) AS goalsFor', $mine))
            ->addSelect(\sprintf('SUM(%s) AS goalsAgainst', $theirs))
            ->where('f.season = :season')
            ->andWhere('f.status = :finished')
            ->groupBy(\sprintf('f.%s', $side))
            ->setParameter('season', $season)
            ->setParameter('finished', MatchStatus::Finished)
            ->getQuery()
            ->getArrayResult();

        // Every aggregate arrives as a string: PostgreSQL returns COUNT and SUM as bigint, and
        // PHP has no 64-bit integer type in the driver, so the value is handed over as text.
        // Casting here rather than at the point of use keeps the arithmetic honest.
        return array_map(
            static fn (array $row): SideAggregate => new SideAggregate(
                teamId: (int) $row['teamId'],
                played: (int) $row['played'],
                won: (int) $row['won'],
                drawn: (int) $row['drawn'],
                lost: (int) $row['lost'],
                goalsFor: (int) $row['goalsFor'],
                goalsAgainst: (int) $row['goalsAgainst'],
            ),
            $rows,
        );
    }

    /**
     * Matches that kick off inside a window, with everything a reminder needs to be written.
     *
     * Only SCHEDULED: a match already being played needs no reminder, and a cancelled or
     * postponed one keeps its old kick-off time in the column, so filtering by time alone
     * would remind people about matches that are not going to happen.
     *
     * The joins are not decoration — writing one reminder reads both club names, the season
     * and the league, and a hundred matches in a window would otherwise be five hundred
     * queries in a background worker nobody is watching.
     *
     * @return list<Fixture>
     */
    public function findKickingOffBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<Fixture> $fixtures */
        $fixtures = $this->createQueryBuilder('f')
            ->addSelect('home', 'away', 's', 'l')
            ->innerJoin('f.homeTeam', 'home')
            ->innerJoin('f.awayTeam', 'away')
            ->innerJoin('f.season', 's')
            ->innerJoin('s.league', 'l')
            ->where('f.status = :scheduled')
            ->andWhere('f.kickOffAt >= :from')
            ->andWhere('f.kickOffAt < :to')
            ->orderBy('f.kickOffAt', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->setParameter('scheduled', MatchStatus::Scheduled)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        return $fixtures;
    }
}
