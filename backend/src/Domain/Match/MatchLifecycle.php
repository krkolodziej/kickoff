<?php

declare(strict_types=1);

namespace App\Domain\Match;

use App\Entity\Fixture;
use App\Entity\MatchStatus;
use App\Exception\InvalidTransitionException;
use App\Message\MatchFinished;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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
        private readonly MessageBusInterface $bus,
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

        // The state change and the announcement of it go in one transaction, and that is the
        // point of choosing the Doctrine transport over Redis or AMQP.
        //
        // `dispatch` on this transport is an INSERT into `messenger_messages`, on this
        // connection, inside this transaction. So if the flush below fails — or anything
        // between here and the commit does — the message disappears with the change it was
        // announcing. There is no window in which the world has been told about a result the
        // database does not have.
        //
        // A broker outside the database cannot offer that. Sending before the commit risks a
        // notification about a result that never landed; sending after risks a result nobody
        // is told about, because the process can die in between. Django solves it with
        // `transaction.on_commit`; here the transport's own storage solves it structurally.
        $this->entityManager->wrapInTransaction(function () use ($fixture, $target): void {
            $fixture->setStatus($target);
            $this->applySideEffects($fixture, $target);

            $this->entityManager->flush();

            if (MatchStatus::Finished === $target) {
                $this->bus->dispatch(new MatchFinished((int) $fixture->getId()));
            }
        });
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
