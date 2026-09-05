<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\League;

final readonly class LeagueResource
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $name,
        public string $slug,
        public string $description,
        public \DateTimeImmutable $createdAt,
        /**
         * A league on its own is a name. What makes it worth opening is that there are
         * seasons inside it, so the list says how many and which one is current — enough for
         * a row to link straight at the season rather than at another list.
         */
        public int $seasonCount = 0,
        public ?SeasonRefResource $latestSeason = null,
    ) {
    }

    public static function fromEntity(
        League $league,
        int $seasonCount = 0,
        ?SeasonRefResource $latestSeason = null,
    ): self {
        return new self(
            id: (int) $league->getId(),
            organizationId: (int) $league->getOrganization()->getId(),
            name: $league->getName(),
            slug: $league->getSlug(),
            description: $league->getDescription(),
            createdAt: $league->getCreatedAt(),
            seasonCount: $seasonCount,
            latestSeason: $latestSeason,
        );
    }
}
