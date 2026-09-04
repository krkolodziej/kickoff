<?php

declare(strict_types=1);

namespace App\Dto\Output;

/**
 * One player's season, counted from match events.
 *
 * There is no appearances column, and its absence is deliberate. Nothing in the model records
 * who took the field: an event exists only when a player did something, so counting the
 * matches a player has events in would report a defender who never scored or was booked as
 * having played nothing. A column that is wrong for most of a squad is worse than a column
 * that is missing, so appearances waits for a line-up to be modelled.
 */
final readonly class PlayerStatisticsRow
{
    public function __construct(
        public int $playerId,
        public string $firstName,
        public string $lastName,
        public int $teamId,
        public string $teamName,
        public int $goals,
        public int $yellowCards,
        public int $redCards,
    ) {
    }
}
