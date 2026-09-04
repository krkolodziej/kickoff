<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Output\PlayerStatisticsRow;
use App\Entity\Fixture;
use App\Entity\MatchEvent;
use App\Entity\MatchEventType;
use App\Entity\MatchStatus;
use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchEvent>
 */
class MatchEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchEvent::class);
    }

    /**
     * The timeline, in the order it happened.
     *
     * `addSelect` on the player, the club and the substitute: a timeline renders every one of
     * those names, and without it a fifteen-event match is fifty queries.
     *
     * The tiebreaker on id matters more than usual here — two goals in the same minute are
     * common, and their order in the list should at least be stable between refreshes.
     *
     * @return list<MatchEvent>
     */
    public function findForFixture(Fixture $fixture): array
    {
        /* @var list<MatchEvent> */
        return $this->createQueryBuilder('e')
            ->addSelect('p', 't', 'rp')
            ->innerJoin('e.player', 'p')
            ->innerJoin('e.team', 't')
            ->leftJoin('e.relatedPlayer', 'rp')
            ->where('e.fixture = :fixture')
            ->orderBy('e.minute', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->setParameter('fixture', $fixture)
            ->getQuery()
            ->getResult();
    }

    /**
     * Every player who did something in a season, with what they did.
     *
     * One query, one GROUP BY, one row per player: counting goals and cards separately would
     * be three round trips and a merge, and conditional aggregation gets all three in a single
     * pass over the same rows.
     *
     * Which matches count is {@see MatchStatus::countedInStatistics()} — live ones do, so a
     * top-scorer list moves while a match is being played, while the league table waits for
     * full time.
     *
     * The ordering is settled in SQL down to the player id. Two players level on goals must
     * come back in the same order every time, or a list that is entirely correct still looks
     * broken when it reshuffles on refresh.
     *
     * @return list<PlayerStatisticsRow>
     */
    public function seasonPlayerTotals(Season $season): array
    {
        $count = static fn (string $alias, string $type): string => \sprintf(
            "SUM(CASE WHEN e.type = '%s' THEN 1 ELSE 0 END) AS %s",
            $type,
            $alias,
        );

        /** @var list<array{playerId: int|string, firstName: string, lastName: string, teamId: int|string, teamName: string, goals: int|string, yellowCards: int|string, redCards: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('p.id AS playerId', 'p.firstName AS firstName', 'p.lastName AS lastName')
            ->addSelect('t.id AS teamId', 't.name AS teamName')
            ->addSelect($count('goals', MatchEventType::Goal->value))
            ->addSelect($count('yellowCards', MatchEventType::YellowCard->value))
            ->addSelect($count('redCards', MatchEventType::RedCard->value))
            ->innerJoin('e.fixture', 'f')
            ->innerJoin('e.player', 'p')
            ->innerJoin('e.team', 't')
            ->where('f.season = :season')
            ->andWhere('f.status IN (:played)')
            ->groupBy('p.id')
            ->addGroupBy('p.firstName')
            ->addGroupBy('p.lastName')
            ->addGroupBy('t.id')
            ->addGroupBy('t.name')
            ->orderBy('goals', 'DESC')
            ->addOrderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setParameter('season', $season)
            ->setParameter('played', MatchStatus::countedInStatistics())
            ->getQuery()
            ->getArrayResult();

        // COUNT and SUM come back from PostgreSQL as bigint, which the driver hands over as
        // text. Casting at the boundary keeps everything above this line dealing in integers.
        return array_map(
            static fn (array $row): PlayerStatisticsRow => new PlayerStatisticsRow(
                playerId: (int) $row['playerId'],
                firstName: $row['firstName'],
                lastName: $row['lastName'],
                teamId: (int) $row['teamId'],
                teamName: $row['teamName'],
                goals: (int) $row['goals'],
                yellowCards: (int) $row['yellowCards'],
                redCards: (int) $row['redCards'],
            ),
            $rows,
        );
    }
}
