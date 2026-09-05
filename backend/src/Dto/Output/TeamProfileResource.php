<?php

declare(strict_types=1);

namespace App\Dto\Output;

/**
 * Everything one club's page needs, in one response.
 *
 * @see \App\Domain\Club\ClubProfile for how it is assembled and what it costs.
 */
final readonly class TeamProfileResource
{
    /**
     * @param list<RosterEntryResource> $squad   the latest season's squad, empty when the club
     *                                           has never been registered
     * @param list<ClubSeasonRow>       $seasons newest first
     */
    public function __construct(
        public TeamResource $team,
        public ?int $latestSeasonId,
        public array $squad,
        public array $seasons,
    ) {
    }
}
