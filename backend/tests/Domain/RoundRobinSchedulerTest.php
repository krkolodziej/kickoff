<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Fixture\FixturePairing;
use App\Domain\Fixture\RoundRobinScheduler;
use App\Exception\SchedulingException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `TestCase`, not `KernelTestCase`. No kernel, no database, no fixtures — the scheduler is a
 * pure function and this whole file runs in single-digit milliseconds, which is what makes it
 * reasonable to test the algorithm exhaustively rather than on one happy case.
 */
final class RoundRobinSchedulerTest extends TestCase
{
    private RoundRobinScheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new RoundRobinScheduler();
    }

    #[DataProvider('evenClubCounts')]
    public function testEveryPairMeetsExactlyOnce(int $clubs): void
    {
        $pairings = $this->scheduler->schedule(range(1, $clubs));

        $expectedFixtures = $clubs * ($clubs - 1) / 2;
        self::assertCount((int) $expectedFixtures, $pairings);

        $met = array_map(static fn (FixturePairing $p): string => $p->pairKey(), $pairings);
        self::assertCount((int) $expectedFixtures, array_unique($met), 'No pair meets twice.');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function evenClubCounts(): iterable
    {
        yield '2 clubs' => [2];
        yield '4 clubs' => [4];
        yield '12 clubs' => [12];
        yield '20 clubs' => [20];
    }

    public function testTwelveClubsPlayElevenRoundsOfSix(): void
    {
        $pairings = $this->scheduler->schedule(range(1, 12));

        self::assertCount(66, $pairings);
        self::assertSame(11, $this->scheduler->roundCount(12));

        $byRound = $this->groupByRound($pairings);
        self::assertCount(11, $byRound);

        foreach ($byRound as $round => $fixtures) {
            self::assertCount(6, $fixtures, \sprintf('Round %d should have six fixtures.', $round));
        }
    }

    #[DataProvider('everyClubCount')]
    public function testNobodyPlaysTwiceInTheSameRound(int $clubs): void
    {
        foreach ($this->groupByRound($this->scheduler->schedule(range(1, $clubs))) as $round => $fixtures) {
            $appearances = [];

            foreach ($fixtures as $fixture) {
                $appearances[] = $fixture->homeTeamId;
                $appearances[] = $fixture->awayTeamId;
            }

            self::assertCount(
                \count(array_unique($appearances)),
                $appearances,
                \sprintf('A club appears twice in round %d.', $round),
            );
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function everyClubCount(): iterable
    {
        foreach (range(2, 16) as $clubs) {
            yield $clubs.' clubs' => [$clubs];
        }
    }

    /**
     * An odd number of clubs means somebody sits out every round, and everybody has to take
     * a turn — otherwise one club plays fewer games than the rest and the table is a lie.
     */
    public function testAnOddNumberOfClubsLeavesExactlyOneIdlePerRound(): void
    {
        $clubs = 11;
        $byRound = $this->groupByRound($this->scheduler->schedule(range(1, $clubs)));

        self::assertCount(11, $byRound, 'Eleven clubs still need eleven rounds.');

        $restCount = [];

        foreach ($byRound as $round => $fixtures) {
            self::assertCount(5, $fixtures, \sprintf('Round %d should have five fixtures.', $round));

            $playing = [];

            foreach ($fixtures as $fixture) {
                $playing[] = $fixture->homeTeamId;
                $playing[] = $fixture->awayTeamId;
            }

            $resting = array_values(array_diff(range(1, $clubs), $playing));
            self::assertCount(1, $resting);
            $restCount[$resting[0]] = ($restCount[$resting[0]] ?? 0) + 1;
        }

        self::assertCount($clubs, $restCount, 'Every club takes exactly one round off.');
    }

    public function testADoubleRoundIsTheFirstLegMirrored(): void
    {
        $pairings = $this->scheduler->schedule(range(1, 12), doubleRound: true);

        self::assertCount(132, $pairings);
        self::assertSame(22, $this->scheduler->roundCount(12, doubleRound: true));

        $first = array_values(array_filter($pairings, static fn (FixturePairing $p): bool => 1 === $p->leg));
        $second = array_values(array_filter($pairings, static fn (FixturePairing $p): bool => 2 === $p->leg));

        self::assertCount(66, $first);
        self::assertCount(66, $second);

        foreach ($first as $index => $home) {
            $return = $second[$index];

            self::assertSame($home->awayTeamId, $return->homeTeamId, 'The away side hosts the return.');
            self::assertSame($home->homeTeamId, $return->awayTeamId);
            self::assertSame($home->roundNumber + 11, $return->roundNumber);
        }
    }

    public function testEveryClubHostsEveryOtherExactlyOnceOverTwoLegs(): void
    {
        $pairings = $this->scheduler->schedule(range(1, 8), doubleRound: true);

        $directed = array_map(
            static fn (FixturePairing $p): string => $p->homeTeamId.'>'.$p->awayTeamId,
            $pairings,
        );

        self::assertCount(56, $directed, '8 clubs x 7 opponents, home and away.');
        self::assertCount(56, array_unique($directed), 'No direction repeats.');
    }

    /**
     * The bug this test was written to catch, and did.
     *
     * The obvious parity rule leaves the lowest-numbered club away in every round of the
     * season — 0 home games out of 11, and nothing else about the calendar looks wrong. The
     * assertion is deliberately tight: 11 games cannot split evenly, so 5 or 6 is the only
     * honest answer and anything else is a bug.
     */
    #[DataProvider('everyClubCount')]
    public function testHomeAndAwayAreSharedWithinHalfAGame(int $clubs): void
    {
        $pairings = $this->scheduler->schedule(range(1, $clubs));

        $homeGames = [];
        $totalGames = [];

        foreach ($pairings as $pairing) {
            $homeGames[$pairing->homeTeamId] = ($homeGames[$pairing->homeTeamId] ?? 0) + 1;
            $totalGames[$pairing->homeTeamId] = ($totalGames[$pairing->homeTeamId] ?? 0) + 1;
            $totalGames[$pairing->awayTeamId] = ($totalGames[$pairing->awayTeamId] ?? 0) + 1;
        }

        foreach (range(1, $clubs) as $club) {
            $home = $homeGames[$club] ?? 0;
            $played = $totalGames[$club] ?? 0;

            self::assertLessThanOrEqual(
                0.5,
                abs($home - $played / 2),
                \sprintf('Club %d hosts %d of its %d games.', $club, $home, $played),
            );
        }
    }

    /**
     * Over two legs the split is exact, because every pairing is played both ways.
     */
    public function testOverADoubleRoundEveryClubHostsExactlyHalfItsGames(): void
    {
        $pairings = $this->scheduler->schedule(range(1, 12), doubleRound: true);

        $homeGames = [];

        foreach ($pairings as $pairing) {
            $homeGames[$pairing->homeTeamId] = ($homeGames[$pairing->homeTeamId] ?? 0) + 1;
        }

        foreach (range(1, 12) as $club) {
            self::assertSame(11, $homeGames[$club] ?? 0, \sprintf('Club %d should host 11 of 22.', $club));
        }
    }

    /**
     * The same clubs must always produce the same calendar. A generator whose output depends
     * on the order rows happened to come back in cannot be reasoned about, and cannot be
     * tested twice.
     */
    public function testTheCalendarDoesNotDependOnTheOrderTheClubsArriveIn(): void
    {
        $ordered = $this->scheduler->schedule([1, 2, 3, 4, 5, 6]);
        $shuffled = $this->scheduler->schedule([5, 3, 6, 1, 4, 2]);

        self::assertSame(
            array_map(static fn (FixturePairing $p): string => $p->roundNumber.':'.$p->homeTeamId.'>'.$p->awayTeamId, $ordered),
            array_map(static fn (FixturePairing $p): string => $p->roundNumber.':'.$p->homeTeamId.'>'.$p->awayTeamId, $shuffled),
        );
    }

    public function testTwoClubsPlayOnce(): void
    {
        $pairings = $this->scheduler->schedule([7, 9]);

        self::assertCount(1, $pairings);
        self::assertSame(1, $pairings[0]->roundNumber);
    }

    public function testOneClubCannotBeScheduled(): void
    {
        $this->expectException(SchedulingException::class);

        $this->scheduler->schedule([1]);
    }

    public function testADuplicatedClubIsRefused(): void
    {
        $this->expectException(SchedulingException::class);

        $this->scheduler->schedule([1, 2, 2, 3]);
    }

    /**
     * @param list<FixturePairing> $pairings
     *
     * @return array<int, list<FixturePairing>>
     */
    private function groupByRound(array $pairings): array
    {
        $byRound = [];

        foreach ($pairings as $pairing) {
            $byRound[$pairing->roundNumber][] = $pairing;
        }

        ksort($byRound);

        return $byRound;
    }
}
