<?php

declare(strict_types=1);

namespace App\Domain\Match;

use App\Entity\Fixture;
use App\Entity\MatchStatus;
use App\Exception\InvalidTransitionException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Moving a match between states, and the side effects that belong to each move.
 *
 * The table itself lives on MatchStatus; this class is what happens *around* a legal move:
 * the kick-off time, the final whistle, and the flush.
 *
 * Note where "now" comes from. `ClockInterface`, injected, never `new DateTimeImmutable()`.
 * That is not ceremony — the reminder job in a later stage has to answer "which matches kick
 * off in roughly 24 hours", and the only sane way to test that is to fix the clock. Reach for
 * the real one here and the test becomes a sleep.
 */
final class MatchLifecycle
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public function start(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Live);
    }

    public function finish(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Finished);
    }

    public function cancel(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Cancelled);
    }

    public function postpone(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Postponed);
    }

    public function reschedule(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Scheduled);
    }

    public function transition(Fixture $fixture, MatchStatus $target): void
    {
        $from = $fixture->getStatus();

        if (!$from->canTransitionTo($target)) {
            throw new InvalidTransitionException($from, $target);
        }

        $fixture->setStatus($target);
        $this->applySideEffects($fixture, $target);

        $this->entityManager->flush();
    }

    private function applySideEffects(Fixture $fixture, MatchStatus $target): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        match ($target) {
            // `?? $now` and not plain assignment: a match that was postponed at half time and
            // resumed keeps the time it actually kicked off, which is what the minute of every
            // event already recorded is measured from.
            MatchStatus::Live => $fixture->setStartedAt($fixture->getStartedAt() ?? $now),
            MatchStatus::Finished => $fixture->setFinishedAt($now),
            // Going back to the calendar means it has not started, so the clock is cleared.
            // Leaving a stale kick-off behind would make a rescheduled match look played.
            MatchStatus::Scheduled => $fixture->setStartedAt(null),
            default => null,
        };
    }
}
