<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\SeasonTeam;

final readonly class SeasonTeamResource
{
    public function __construct(
        public int $id,
        public int $seasonId,
        public int $teamId,
        public string $teamName,
        public string $teamShortName,
        public int $squadSize,
    ) {
    }

    public static function fromEntity(SeasonTeam $seasonTeam, int $squadSize): self
    {
        $team = $seasonTeam->getTeam();

        return new self(
            id: (int) $seasonTeam->getId(),
            seasonId: (int) $seasonTeam->getSeason()->getId(),
            teamId: (int) $team->getId(),
            teamName: $team->getName(),
            teamShortName: $team->getShortName(),
            squadSize: $squadSize,
        );
    }
}
