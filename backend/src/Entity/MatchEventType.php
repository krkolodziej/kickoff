<?php

declare(strict_types=1);

namespace App\Entity;

enum MatchEventType: string
{
    case Goal = 'GOAL';
    case YellowCard = 'YELLOW_CARD';
    case RedCard = 'RED_CARD';
    case Substitution = 'SUBSTITUTION';

    /** Only a substitution involves a second player: the one coming on. */
    public function needsRelatedPlayer(): bool
    {
        return self::Substitution === $this;
    }

    public function movesTheScore(): bool
    {
        return self::Goal === $this;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
