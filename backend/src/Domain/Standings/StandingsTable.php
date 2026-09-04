<?php

declare(strict_types=1);

namespace App\Domain\Standings;

use App\Dto\Output\StandingRow;

/**
 * Turns per-side aggregates into a league table.
 *
 * Deliberately free of Doctrine, for the reason `RoundRobinScheduler` is: a table that is
 * subtly wrong is not noticed for weeks, and the only defence is a test that runs often
 * enough to be run. Feeding this arrays instead of a database keeps the whole suite in
 * milliseconds, so it can afford to check orderings exhaustively rather than pick one.
 */
final class StandingsTable
{
    /**
     * Three for a win, one for a draw — a constant rather than a literal buried in an
     * expression, because it is the one rule of the table a reader will look for.
     */
    public const POINTS_FOR_A_WIN = 3;
    public const POINTS_FOR_A_DRAW = 1;

    /**
     * @param array<int, string> $clubs        team id => display name, every club registered
     *                                         for the season
     * @param list<SideAggregate> $aggregates  home-side and away-side rows, in any order
     *
     * @return list<StandingRow>
     */
    public static function build(array $clubs, array $aggregates): array
    {
        // Seeded from the registered clubs, not from the results. A club that has not yet
        // played sits at the bottom with zeros; building from the results instead would make
        // it vanish from its own league until somebody scheduled it.
        $totals = [];

        foreach ($clubs as $teamId => $name) {
            $totals[$teamId] = [
                'name' => $name,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goalsFor' => 0,
                'goalsAgainst' => 0,
            ];
        }

        foreach ($aggregates as $side) {
            // A result for a club that is not registered for this season cannot be placed,
            // and inventing a row for it would be worse than dropping it.
            if (!isset($totals[$side->teamId])) {
                continue;
            }

            $totals[$side->teamId]['played'] += $side->played;
            $totals[$side->teamId]['won'] += $side->won;
            $totals[$side->teamId]['drawn'] += $side->drawn;
            $totals[$side->teamId]['lost'] += $side->lost;
            $totals[$side->teamId]['goalsFor'] += $side->goalsFor;
            $totals[$side->teamId]['goalsAgainst'] += $side->goalsAgainst;
        }

        $rows = [];

        foreach ($totals as $teamId => $t) {
            $rows[] = [
                'teamId' => $teamId,
                'name' => $t['name'],
                'played' => $t['played'],
                'won' => $t['won'],
                'drawn' => $t['drawn'],
                'lost' => $t['lost'],
                'goalsFor' => $t['goalsFor'],
                'goalsAgainst' => $t['goalsAgainst'],
                'goalDifference' => $t['goalsFor'] - $t['goalsAgainst'],
                'points' => $t['won'] * self::POINTS_FOR_A_WIN + $t['drawn'] * self::POINTS_FOR_A_DRAW,
            ];
        }

        usort($rows, self::compare(...));

        return array_map(
            static fn (array $row, int $index): StandingRow => new StandingRow(
                position: $index + 1,
                teamId: $row['teamId'],
                teamName: $row['name'],
                played: $row['played'],
                won: $row['won'],
                drawn: $row['drawn'],
                lost: $row['lost'],
                goalsFor: $row['goalsFor'],
                goalsAgainst: $row['goalsAgainst'],
                goalDifference: $row['goalDifference'],
                points: $row['points'],
            ),
            $rows,
            array_keys($rows),
        );
    }

    /**
     * Points, then goal difference, then goals scored, then name, then id.
     *
     * The last two are not tie-breakers anybody plays for: they are there so that two clubs
     * level on everything come back in the same order on every request. Without them the
     * database is free to return either first, and a table that reshuffles on refresh looks
     * broken even when every number in it is right.
     *
     * @param array{points: int, goalDifference: int, goalsFor: int, name: string, teamId: int} $a
     * @param array{points: int, goalDifference: int, goalsFor: int, name: string, teamId: int} $b
     */
    private static function compare(array $a, array $b): int
    {
        return $b['points'] <=> $a['points']
            ?: $b['goalDifference'] <=> $a['goalDifference']
            ?: $b['goalsFor'] <=> $a['goalsFor']
            ?: $a['name'] <=> $b['name']
            ?: $a['teamId'] <=> $b['teamId'];
    }
}
