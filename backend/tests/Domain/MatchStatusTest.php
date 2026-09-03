<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\MatchStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The transition table, checked in full — all twenty-five combinations, not just the ones
 * somebody remembered to try. It is data, so this costs nothing and runs without a kernel.
 */
final class MatchStatusTest extends TestCase
{
    /**
     * Written out rather than derived from the enum, on purpose: a test that asks the code
     * what the rules are agrees with any bug the code has. This is the specification.
     *
     * @return iterable<string, array{MatchStatus, MatchStatus, bool}>
     */
    public static function everyCombination(): iterable
    {
        $allowed = [
            'SCHEDULED' => ['LIVE', 'CANCELLED', 'POSTPONED'],
            'POSTPONED' => ['SCHEDULED', 'LIVE', 'CANCELLED'],
            'LIVE' => ['FINISHED', 'CANCELLED', 'POSTPONED'],
            'FINISHED' => [],
            'CANCELLED' => [],
        ];

        foreach (MatchStatus::cases() as $from) {
            foreach (MatchStatus::cases() as $to) {
                $expected = \in_array($to->value, $allowed[$from->value], true);

                yield \sprintf('%s -> %s', $from->value, $to->value) => [$from, $to, $expected];
            }
        }
    }

    #[DataProvider('everyCombination')]
    public function testTheTableIsExactlyWhatWeSaidItIs(
        MatchStatus $from,
        MatchStatus $to,
        bool $expected,
    ): void {
        self::assertSame(
            $expected,
            $from->canTransitionTo($to),
            \sprintf('%s -> %s', $from->value, $to->value),
        );
    }

    /**
     * A state cannot lead to itself. Without this, "start" on a live match would look like a
     * harmless no-op and the button would never be disabled.
     */
    public function testNoStateTransitionsToItself(): void
    {
        foreach (MatchStatus::cases() as $status) {
            self::assertFalse($status->canTransitionTo($status), $status->value.' -> itself');
        }
    }

    public function testOnlyFinishedAndCancelledAreDeadEnds(): void
    {
        $terminal = array_values(array_filter(
            MatchStatus::cases(),
            static fn (MatchStatus $status): bool => $status->isTerminal(),
        ));

        self::assertSame([MatchStatus::Finished, MatchStatus::Cancelled], $terminal);
    }

    /**
     * A postponed match has to be able to come back, otherwise a rained-off game is lost for
     * the season — the reason POSTPONED is not terminal.
     */
    public function testAPostponedMatchCanComeBack(): void
    {
        self::assertTrue(MatchStatus::Postponed->canTransitionTo(MatchStatus::Scheduled));
        self::assertTrue(MatchStatus::Postponed->canTransitionTo(MatchStatus::Live));
    }

    public function testAFinishedMatchIsNotReopened(): void
    {
        self::assertSame([], MatchStatus::Finished->allowedTransitions());
        self::assertFalse(MatchStatus::Finished->canTransitionTo(MatchStatus::Live));
    }
}
