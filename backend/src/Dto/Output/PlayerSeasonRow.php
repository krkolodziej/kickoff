<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\PlayerPosition;

/**
 * One line of a player's career: a season, the club they turned out for, and what they did.
 *
 * The registration facts and the counted facts come from two different places — a roster
 * entry and the match events — which is why this row exists rather than the profile handing
 * back a roster entry and leaving the client to line the totals up against it.
 */
final readonly class PlayerSeasonRow
{
    public function __construct(
        public int $seasonId,
        public string $seasonName,
        public int $leagueId,
        public string $leagueName,
        public int $teamId,
        public string $teamName,
        public ?int $shirtNumber,
        public ?PlayerPosition $position,
        public bool $captain,
        public int $goals,
        public int $yellowCards,
        public int $redCards,
    ) {
    }
}
