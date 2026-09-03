<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\PlayerPosition;
use App\Entity\RosterEntry;

final readonly class RosterEntryResource
{
    public function __construct(
        public int $id,
        public int $seasonTeamId,
        public int $playerId,
        public string $playerName,
        public ?int $shirtNumber,
        public ?PlayerPosition $position,
        public bool $captain,
    ) {
    }

    public static function fromEntity(RosterEntry $entry): self
    {
        return new self(
            id: (int) $entry->getId(),
            seasonTeamId: (int) $entry->getSeasonTeam()->getId(),
            playerId: (int) $entry->getPlayer()->getId(),
            playerName: $entry->getPlayer()->getFullName(),
            shirtNumber: $entry->getShirtNumber(),
            position: $entry->getPosition(),
            captain: $entry->isCaptain(),
        );
    }
}
