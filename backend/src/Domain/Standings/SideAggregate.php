<?php

declare(strict_types=1);

namespace App\Domain\Standings;

/**
 * One club's results from one side of the pitch.
 *
 * The database can only aggregate a fixture from the home column or the away column, never
 * both at once, so a club's season arrives as two of these and is added up afterwards. This
 * type exists so that addition happens over something named rather than over an array whose
 * keys have to be remembered.
 */
final readonly class SideAggregate
{
    public function __construct(
        public int $teamId,
        public int $played,
        public int $won,
        public int $drawn,
        public int $lost,
        public int $goalsFor,
        public int $goalsAgainst,
    ) {
    }
}
