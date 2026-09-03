<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Where a fixture is in its life, and where it may go next.
 *
 * The transition table is **data**, not a pile of `if`s spread across a service. That is the
 * whole trick: the rules can be read in one place, tested exhaustively, and handed to the
 * client so a button is disabled rather than disappointing.
 *
 * Why not `symfony/workflow`? It is the right tool once a transition has guards, listeners,
 * and metadata — an order that emails a warehouse, say. Here the entire machine is the array
 * below. The bundle would add a configuration file, a service, a vocabulary and an
 * indirection, in exchange for replacing nine lines. Worth knowing it exists and worth being
 * able to say why it is not here.
 */
enum MatchStatus: string
{
    case Scheduled = 'SCHEDULED';
    case Live = 'LIVE';
    case Finished = 'FINISHED';
    case Cancelled = 'CANCELLED';
    case Postponed = 'POSTPONED';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // A postponed match can come back to the calendar, be played straight away, or
            // be abandoned — which is why POSTPONED is not a dead end.
            self::Scheduled => [self::Live, self::Cancelled, self::Postponed],
            self::Postponed => [self::Scheduled, self::Live, self::Cancelled],
            self::Live => [self::Finished, self::Cancelled, self::Postponed],
            // Terminal. A finished match is re-opened by correcting its events, never by
            // moving it backwards, and a cancelled one is a fixture that will not be played.
            self::Finished, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return [] === $this->allowedTransitions();
    }

    /**
     * @return list<string>
     */
    public function allowedTransitionValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, $this->allowedTransitions());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
