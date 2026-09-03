<?php

declare(strict_types=1);

namespace App\Domain\Fixture;

use App\Exception\SchedulingException;

/**
 * The circle method: every club meets every other exactly once.
 *
 * No Doctrine, no entities, no container — ids in, pairings out. That is not tidiness for
 * its own sake. A scheduling bug is the kind that shows up as "round 14 looks wrong" weeks
 * later, and the only way to be confident is to test the algorithm exhaustively: every pair
 * meets once, nobody plays twice in a round, the byes land where they should. That test
 * suite has to be instant, and it is only instant if nothing here touches a database.
 *
 * How it works, in case the modular arithmetic is not self-evident: one club is pinned and
 * the rest rotate around it. With `n` clubs there are `n - 1` rounds, and in each round the
 * pinned club plays whoever has rotated into the first slot while the remaining clubs pair
 * off inwards from the ends.
 *
 * An odd number of clubs is handled by adding a placeholder that nobody can play; the club
 * drawn against it simply has no fixture that round. The rounds still number `n`, because
 * every club needs a week off.
 */
final class RoundRobinScheduler
{
    /** Stands in for the missing club when there is an odd number of them. */
    private const BYE = -1;

    /**
     * @param list<int> $teamIds
     *
     * @return list<FixturePairing>
     */
    public function schedule(array $teamIds, bool $doubleRound = false): array
    {
        $teamIds = $this->validated($teamIds);

        $slots = $teamIds;

        // The placeholder makes the count even, which is what lets the pairing loop below
        // stay a single case instead of two.
        if (0 !== \count($slots) % 2) {
            $slots[] = self::BYE;
        }

        $count = \count($slots);
        $rounds = $count - 1;
        $half = intdiv($count, 2);

        $firstLeg = [];

        for ($round = 0; $round < $rounds; ++$round) {
            for ($index = 0; $index < $half; ++$index) {
                [$first, $second] = $this->pairFor($slots, $round, $index, $rounds);

                if (self::BYE === $first || self::BYE === $second) {
                    continue;
                }

                $swap = $this->awaySideFirst($round, $index);

                $firstLeg[] = new FixturePairing(
                    roundNumber: $round + 1,
                    leg: 1,
                    homeTeamId: $swap ? $second : $first,
                    awayTeamId: $swap ? $first : $second,
                );
            }
        }

        if (!$doubleRound) {
            return $firstLeg;
        }

        // The return fixtures, in the same order, with the sides reversed: whoever was at
        // home in round 3 is away in round 3 + rounds.
        $secondLeg = array_map(
            static fn (FixturePairing $pairing): FixturePairing => $pairing->reversed(
                $pairing->roundNumber + $rounds,
            ),
            $firstLeg,
        );

        return [...$firstLeg, ...$secondLeg];
    }

    /**
     * How many rounds a given number of clubs takes. Needed by callers that want to space
     * the calendar out before any pairing exists.
     */
    public function roundCount(int $teamCount, bool $doubleRound = false): int
    {
        $slots = 0 === $teamCount % 2 ? $teamCount : $teamCount + 1;
        $rounds = max(0, $slots - 1);

        return $doubleRound ? $rounds * 2 : $rounds;
    }

    /**
     * Which of the two sides hosts.
     *
     * Getting this wrong is the classic circle-method bug, and it is invisible unless you
     * count: the obvious `(round + index) % 2` leaves the *lowest-numbered* club away in
     * every single round of the season, because the two positions it can occupy both land on
     * the same parity. Measured across 4 to 20 clubs, it puts one club on 0 home games out of
     * 19.
     *
     * The rule below was picked by measuring the alternatives rather than by reasoning about
     * them. It gives every club within half a game of an even split — 5 or 6 home games out of
     * 11 for a twelve-club league, and exactly 11 of 22 over a double round.
     *
     * The pinned pair alternates by round, because the pinned club is in every round and
     * nothing else varies for it. The rest alternate by their position in the round, because
     * the clubs rotate through those positions.
     */
    private function awaySideFirst(int $round, int $index): bool
    {
        return 0 === $index ? 1 === $round % 2 : 1 === $index % 2;
    }

    /**
     * @param list<int> $slots
     *
     * @return array{int, int}
     */
    private function pairFor(array $slots, int $round, int $index, int $rotating): array
    {
        $pinned = \count($slots) - 1;

        if (0 === $index) {
            return [$slots[$pinned], $slots[$round % $rotating]];
        }

        return [
            $slots[($round + $index) % $rotating],
            $slots[($round - $index + $rotating) % $rotating],
        ];
    }

    /**
     * @param list<int> $teamIds
     *
     * @return list<int>
     */
    private function validated(array $teamIds): array
    {
        if (\count($teamIds) !== \count(array_unique($teamIds))) {
            throw SchedulingException::duplicateClubs();
        }

        if (\count($teamIds) < 2) {
            throw SchedulingException::notEnoughClubs();
        }

        // Sorted so the same set of clubs always produces the same calendar. A generator
        // whose output depends on the order rows came back in is impossible to reason about
        // and impossible to test twice.
        sort($teamIds);

        return $teamIds;
    }
}
