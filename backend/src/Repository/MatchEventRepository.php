<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Output\PlayerStatisticsRow;
use App\Dto\Output\PlayerTotals;
use App\Entity\Fixture;
use App\Entity\MatchEvent;
use App\Entity\MatchEventType;
use App\Entity\MatchStatus;
use App\Entity\Player;
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
        /** @var list<array{playerId: int|string, firstName: string, lastName: string, teamId: int|string, teamName: string, goals: int|string, yellowCards: int|string, redCards: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('p.id AS playerId', 'p.firstName AS firstName', 'p.lastName AS lastName')
            ->addSelect('t.id AS teamId', 't.name AS teamName')
            ->addSelect(self::countOf('goals', MatchEventType::Goal))
            ->addSelect(self::countOf('yellowCards', MatchEventType::YellowCard))
            ->addSelect(self::countOf('redCards', MatchEventType::RedCard))
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

    /**
     * Career goals and cards for a page of players, in one query.
     *
     * A sibling of {@see seasonPlayerTotals()} — same conditional aggregation, same
     * definition of which matches count — but grouped by player alone and asked for a set of
     * players rather than for a season. A list of players that fetched this per row would be
     * a query per row; a query-count test asserts that it is not.
     *
     * @param list<int> $playerIds
     *
     * @return array<int, PlayerTotals> player id => their totals
     */
    public function careerTotalsForPlayers(array $playerIds): array
    {
        if ([] === $playerIds) {
            return [];
        }

        /** @var list<array{playerId: int|string, goals: int|string, yellowCards: int|string, redCards: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.player) AS playerId')
            ->addSelect(self::countOf('goals', MatchEventType::Goal))
            ->addSelect(self::countOf('yellowCards', MatchEventType::YellowCard))
            ->addSelect(self::countOf('redCards', MatchEventType::RedCard))
            ->innerJoin('e.fixture', 'f')
            ->where('IDENTITY(e.player) IN (:players)')
            ->andWhere('f.status IN (:played)')
            ->groupBy('e.player')
            ->setParameter('players', $playerIds)
            ->setParameter('played', MatchStatus::countedInStatistics())
            ->getQuery()
            ->getArrayResult();

        $totals = [];

        foreach ($rows as $row) {
            // Cast at the boundary: SUM comes back from PostgreSQL as bigint, i.e. as text.
            $playerId = (int) $row['playerId'];
            $totals[$playerId] = new PlayerTotals(
                playerId: $playerId,
                goals: (int) $row['goals'],
                yellowCards: (int) $row['yellowCards'],
                redCards: (int) $row['redCards'],
            );
        }

        return $totals;
    }

    /**
     * One player's goals and cards, broken down by season and club.
     *
     * {@see seasonPlayerTotals()} is the wrong shape for this: it is season-wide and returns
     * every player, so a career of five seasons would be five queries fetching a whole league
     * each time.
     *
     * Keyed by season *and* club rather than by season alone. Events carry a club and roster
     * rows carry a season-team, so the pair is exact unconditionally; season alone happens to
     * be exact today and would stop being so the moment somebody transfers mid-season.
     *
     * @return array<string, PlayerTotals> "seasonId:teamId" => totals
     */
    public function totalsForPlayerBySeason(Player $player): array
    {
        /** @var list<array{seasonId: int|string, teamId: int|string, goals: int|string, yellowCards: int|string, redCards: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(f.season) AS seasonId', 'IDENTITY(e.team) AS teamId')
            ->addSelect(self::countOf('goals', MatchEventType::Goal))
            ->addSelect(self::countOf('yellowCards', MatchEventType::YellowCard))
            ->addSelect(self::countOf('redCards', MatchEventType::RedCard))
            ->innerJoin('e.fixture', 'f')
            ->where('e.player = :player')
            ->andWhere('f.status IN (:played)')
            ->groupBy('f.season')
            ->addGroupBy('e.team')
            ->setParameter('player', $player)
            ->setParameter('played', MatchStatus::countedInStatistics())
            ->getQuery()
            ->getArrayResult();

        $totals = [];

        foreach ($rows as $row) {
            $key = self::seasonTeamKey((int) $row['seasonId'], (int) $row['teamId']);
            $totals[$key] = new PlayerTotals(
                playerId: (int) $player->getId(),
                goals: (int) $row['goals'],
                yellowCards: (int) $row['yellowCards'],
                redCards: (int) $row['redCards'],
            );
        }

        return $totals;
    }

    /**
     * The key {@see totalsForPlayerBySeason()} returns, so that callers do not have to build
     * the same string from memory and get the separator wrong.
     */
    public static function seasonTeamKey(int $seasonId, int $teamId): string
    {
        return $seasonId.':'.$teamId;
    }

    /**
     * Conditional aggregation: counting goals, yellows and reds in one pass over the rows
     * rather than in three round trips that would then have to be merged.
     */
    private static function countOf(string $alias, MatchEventType $type): string
    {
        return \sprintf("SUM(CASE WHEN e.type = '%s' THEN 1 ELSE 0 END) AS %s", $type->value, $alias);
    }
}
