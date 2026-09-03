<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Four buckets, not free text.
 *
 * The application it replaces stored this as a VARCHAR, and the seed data then wrote exactly
 * these four words into it. Free text looks flexible until something has to read it: the
 * scoring model in a later stage weights a goal by position, and "LW", "left wing" and
 * "Lewy pomocnik" are three different positions to a computer and one to a person.
 */
enum PlayerPosition: string
{
    case Goalkeeper = 'GOALKEEPER';
    case Defender = 'DEFENDER';
    case Midfielder = 'MIDFIELDER';
    case Forward = 'FORWARD';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
