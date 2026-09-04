<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Standings\SideAggregate;
use App\Domain\Standings\StandingsTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `TestCase`, not `KernelTestCase` — the table is a pure function of results, so none of this
 * needs a database. That is what makes it affordable to write the tie-breaking rules out one
 * by one instead of trusting one representative case.
 */
final class StandingsTableTest extends TestCase
{
    public function testAClubWithNoResultsStillAppears(): void
    {
        $rows = StandingsTable::build([7 => 'Resovia', 9 => 'Stal'], []);

        self::assertCount(2, $rows);
        self::assertSame(0, $rows[0]->played);
        self::assertSame(0, $rows[0]->points);

        // Alphabetical, because everything above the name is level at zero.
        self::assertSame('Resovia', $rows[0]->teamName);
        self::assertSame('Stal', $rows[1]->teamName);
    }

    public function testPointsAreThreeForAWinAndOneForADraw(): void
    {
        $rows = StandingsTable::build(
            [1 => 'Winner', 2 => 'Drawer'],
            [
                new SideAggregate(teamId: 1, played: 4, won: 3, drawn: 1, lost: 0, goalsFor: 9, goalsAgainst: 2),
                new SideAggregate(teamId: 2, played: 4, won: 0, drawn: 4, lost: 0, goalsFor: 4, goalsAgainst: 4),
            ],
        );

        self::assertSame(10, $rows[0]->points);
        self::assertSame(4, $rows[1]->points);
    }

    /**
     * The database can only group a fixture by its home column or its away column, so a club's
     * season arrives in two pieces. If they were not added together every club would show half
     * a season — and the table would still look entirely plausible.
     */
    public function testHomeAndAwayAggregatesAreAddedTogether(): void
    {
        $rows = StandingsTable::build(
            [1 => 'Resovia'],
            [
                new SideAggregate(teamId: 1, played: 3, won: 2, drawn: 1, lost: 0, goalsFor: 7, goalsAgainst: 3),
                new SideAggregate(teamId: 1, played: 2, won: 0, drawn: 1, lost: 1, goalsFor: 1, goalsAgainst: 4),
            ],
        );

        self::assertSame(5, $rows[0]->played);
        self::assertSame(2, $rows[0]->won);
        self::assertSame(2, $rows[0]->drawn);
        self::assertSame(1, $rows[0]->lost);
        self::assertSame(8, $rows[0]->goalsFor);
        self::assertSame(7, $rows[0]->goalsAgainst);
        self::assertSame(1, $rows[0]->goalDifference);
        self::assertSame(8, $rows[0]->points);
    }

    public function testGoalDifferenceSeparatesClubsLevelOnPoints(): void
    {
        $rows = StandingsTable::build(
            [1 => 'Alpha', 2 => 'Beta'],
            [
                new SideAggregate(teamId: 1, played: 2, won: 2, drawn: 0, lost: 0, goalsFor: 3, goalsAgainst: 1),
                new SideAggregate(teamId: 2, played: 2, won: 2, drawn: 0, lost: 0, goalsFor: 9, goalsAgainst: 1),
            ],
        );

        self::assertSame('Beta', $rows[0]->teamName);
        self::assertSame(8, $rows[0]->goalDifference);
    }

    public function testGoalsScoredSeparatesClubsLevelOnDifference(): void
    {
        $rows = StandingsTable::build(
            [1 => 'Alpha', 2 => 'Beta'],
            [
                new SideAggregate(teamId: 1, played: 2, won: 1, drawn: 1, lost: 0, goalsFor: 2, goalsAgainst: 1),
                new SideAggregate(teamId: 2, played: 2, won: 1, drawn: 1, lost: 0, goalsFor: 6, goalsAgainst: 5),
            ],
        );

        self::assertSame('Beta', $rows[0]->teamName);
    }

    /**
     * Not a tie-breaker anybody plays for. It is here so that the same request twice gives the
     * same table: without it the database may return either club first, and a table that
     * reshuffles on refresh reads as broken even when every number in it is correct.
     */
    public function testClubsLevelOnEverythingAreOrderedByNameThenId(): void
    {
        $level = static fn (int $id): SideAggregate => new SideAggregate(
            teamId: $id,
            played: 1,
            won: 1,
            drawn: 0,
            lost: 0,
            goalsFor: 2,
            goalsAgainst: 0,
        );

        $rows = StandingsTable::build(
            [30 => 'Zebra', 10 => 'Alpha', 20 => 'Alpha'],
            [$level(30), $level(10), $level(20)],
        );

        self::assertSame([10, 20, 30], array_map(static fn (object $r): int => $r->teamId, $rows));
    }

    public function testPositionsAreSequentialFromOne(): void
    {
        $rows = StandingsTable::build([1 => 'A', 2 => 'B', 3 => 'C'], []);

        self::assertSame([1, 2, 3], array_map(static fn (object $r): int => $r->position, $rows));
    }

    /**
     * A result for a club that is not registered for this season has nowhere to go. Inventing
     * a row for it would put a club in a league it never entered, which is worse than the
     * table quietly not counting a fixture that should not have existed.
     */
    public function testResultsForUnregisteredClubsAreIgnored(): void
    {
        $rows = StandingsTable::build(
            [1 => 'Resovia'],
            [new SideAggregate(teamId: 99, played: 1, won: 1, drawn: 0, lost: 0, goalsFor: 5, goalsAgainst: 0)],
        );

        self::assertCount(1, $rows);
        self::assertSame(0, $rows[0]->played);
    }

    public function testAnEmptySeasonProducesAnEmptyTable(): void
    {
        self::assertSame([], StandingsTable::build([], []));
    }

    /**
     * Whatever the results, the table has to add up: every club's points must follow from its
     * own wins and draws, every match played must have ended somehow, and no club may sit
     * above one with more points.
     *
     * @param list<array{int, int, int, int, int, int, int}> $sides
     */
    #[DataProvider('assortedSeasons')]
    public function testTheTableIsInternallyConsistent(array $sides): void
    {
        $clubs = [];

        foreach ($sides as $side) {
            $clubs[$side[0]] = 'Club '.$side[0];
        }

        $rows = StandingsTable::build($clubs, array_map(
            static fn (array $s): SideAggregate => new SideAggregate(...$s),
            $sides,
        ));

        $previous = null;

        foreach ($rows as $row) {
            self::assertSame(
                $row->won * 3 + $row->drawn,
                $row->points,
                'points must follow from wins and draws',
            );
            self::assertSame(
                $row->won + $row->drawn + $row->lost,
                $row->played,
                'every match played ends in a win, a draw or a defeat',
            );
            self::assertSame($row->goalsFor - $row->goalsAgainst, $row->goalDifference);

            if (null !== $previous) {
                self::assertLessThanOrEqual(
                    $previous->points,
                    $row->points,
                    'no club may sit above one with more points',
                );
            }

            $previous = $row;
        }
    }

    /**
     * @return iterable<string, array{list<array{int, int, int, int, int, int, int}>}>
     */
    public static function assortedSeasons(): iterable
    {
        yield 'a two-club season' => [[
            [1, 2, 1, 0, 1, 3, 2],
            [2, 2, 1, 0, 1, 2, 3],
        ]];

        yield 'everyone drew everything' => [[
            [1, 3, 0, 3, 0, 3, 3],
            [2, 3, 0, 3, 0, 3, 3],
            [3, 3, 0, 3, 0, 3, 3],
        ]];

        yield 'one club won the lot' => [[
            [1, 6, 6, 0, 0, 18, 0],
            [2, 6, 0, 0, 6, 0, 9],
            [3, 6, 0, 0, 6, 0, 9],
        ]];

        yield 'nothing has been played' => [[
            [1, 0, 0, 0, 0, 0, 0],
            [2, 0, 0, 0, 0, 0, 0],
        ]];
    }
}
