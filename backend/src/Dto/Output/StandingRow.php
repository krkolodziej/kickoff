<?php

declare(strict_types=1);

namespace App\Dto\Output;

/**
 * One line of the league table.
 *
 * Nothing here is stored. Every number is derived from finished fixtures on each request,
 * which is the whole point: a stored table can disagree with the results it came from, and
 * when it does, there is no way to tell which of the two is lying.
 */
final readonly class StandingRow
{
    public function __construct(
        public int $position,
        public int $teamId,
        public string $teamName,
        public int $played,
        public int $won,
        public int $drawn,
        public int $lost,
        public int $goalsFor,
        public int $goalsAgainst,
        public int $goalDifference,
        public int $points,
    ) {
    }
}
