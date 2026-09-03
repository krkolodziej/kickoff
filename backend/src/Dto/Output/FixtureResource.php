<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Fixture;
use App\Entity\MatchStatus;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class FixtureResource
{
    /**
     * @param list<string> $allowedTransitions
     */
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
        public MatchStatus $status,
        public int $homeScore,
        public int $awayScore,
        #[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
        /**
         * The transitions the server would accept right now.
         *
         * Sent so the client can disable a button instead of offering one that answers 409.
         * The client still duplicates nothing: it reads this list rather than reimplementing
         * the machine, so the two cannot drift apart.
         */
        public array $allowedTransitions,
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
            status: $fixture->getStatus(),
            homeScore: $fixture->getHomeScore(),
            awayScore: $fixture->getAwayScore(),
            startedAt: $fixture->getStartedAt(),
            finishedAt: $fixture->getFinishedAt(),
            allowedTransitions: $fixture->getStatus()->allowedTransitionValues(),
        );
    }
}
