<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Fixture;

final readonly class FixtureResource
{
    public function __construct(
        public int $id,
        public int $seasonId,
        public int $roundNumber,
        public int $leg,
        public int $homeTeamId,
        public string $homeTeamName,
        public string $homeTeamShortName,
        public int $awayTeamId,
        public string $awayTeamName,
        public string $awayTeamShortName,
        public ?\DateTimeImmutable $kickOffAt,
    ) {
    }

    public static function fromEntity(Fixture $fixture): self
    {
        $home = $fixture->getHomeTeam();
        $away = $fixture->getAwayTeam();

        return new self(
            id: (int) $fixture->getId(),
            seasonId: (int) $fixture->getSeason()->getId(),
            roundNumber: $fixture->getRoundNumber(),
            leg: $fixture->getLeg(),
            homeTeamId: (int) $home->getId(),
            homeTeamName: $home->getName(),
            homeTeamShortName: $home->getShortName(),
            awayTeamId: (int) $away->getId(),
            awayTeamName: $away->getName(),
            awayTeamShortName: $away->getShortName(),
            kickOffAt: $fixture->getKickOffAt(),
        );
    }
}
