<?php

declare(strict_types=1);

namespace App\Domain\Standings;

use App\Dto\Output\StandingRow;
use App\Entity\Season;
use App\Repository\FixtureRepository;
use App\Repository\SeasonTeamRepository;

/**
 * The league table for a season.
 *
 * Nothing is stored. The table is worked out from finished fixtures on every request, which
 * is the point: a stored table is a second copy of the truth, and the moment it drifts from
 * the results it was built from there is no way to tell which of the two is wrong. The cost
 * is three queries — the registered clubs, then one aggregate per side of the pitch.
 *
 * The arithmetic and the ordering live in {@see StandingsTable}, which has never heard of
 * Doctrine and is tested without a database.
 */
final readonly class StandingsCalculator
{
    public function __construct(
        private SeasonTeamRepository $seasonTeams,
        private FixtureRepository $fixtures,
    ) {
    }

    /**
     * @return list<StandingRow>
     */
    public function forSeason(Season $season): array
    {
        return StandingsTable::build(
            $this->seasonTeams->namesForSeason($season),
            $this->fixtures->seasonAggregates($season),
        );
    }
}
