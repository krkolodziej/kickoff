<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\PlayerPosition;
use App\Entity\RosterEntry;

/**
 * Where a player currently turns out: club, season, number, position.
 *
 * Nested rather than seven flat `current_*` columns on the player. A player who is not in
 * anybody's squad is then one null check on the client instead of seven, and the serializer's
 * name converter applies recursively so the wire stays snake_case either way.
 *
 * None of this is a property of the person. It is a property of a squad entry, which is why
 * it lives here and not on {@see \App\Entity\Player}.
 */
final readonly class PlayerSquadResource
{
    public function __construct(
        public int $seasonId,
        public string $seasonName,
        public int $leagueId,
        public int $teamId,
        public string $teamName,
        public string $teamShortName,
        public ?int $shirtNumber,
        public ?PlayerPosition $position,
        public bool $captain,
    ) {
    }

    public static function fromEntity(RosterEntry $entry): self
    {
        $seasonTeam = $entry->getSeasonTeam();
        $season = $seasonTeam->getSeason();
        $team = $seasonTeam->getTeam();

        return new self(
            seasonId: (int) $season->getId(),
            seasonName: $season->getName(),
            leagueId: (int) $season->getLeague()->getId(),
            teamId: (int) $team->getId(),
            teamName: $team->getName(),
            teamShortName: $team->getShortName(),
            shirtNumber: $entry->getShirtNumber(),
            position: $entry->getPosition(),
            captain: $entry->isCaptain(),
        );
    }
}
