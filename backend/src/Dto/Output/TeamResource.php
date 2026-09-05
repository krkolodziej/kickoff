<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Team;

final readonly class TeamResource
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $name,
        public string $shortName,
        public string $slug,
        public \DateTimeImmutable $createdAt,
        /**
         * Registration facts, annotated on. A club is registered season by season, so "how
         * big is the squad" is really "how big is the squad *now*" — the count from the most
         * recent season this club appears in, and zero for a club nobody has entered yet.
         *
         * Defaulted for the same reason as on {@see PlayerResource}: a club created a second
         * ago has no seasons, and that is an answer rather than a gap.
         */
        public int $squadSize = 0,
        public int $seasonsPlayed = 0,
        public ?SeasonRefResource $latestSeason = null,
    ) {
    }

    public static function fromEntity(
        Team $team,
        int $squadSize = 0,
        int $seasonsPlayed = 0,
        ?SeasonRefResource $latestSeason = null,
    ): self {
        return new self(
            id: (int) $team->getId(),
            organizationId: (int) $team->getOrganization()->getId(),
            name: $team->getName(),
            // The getter already falls back to the full name, so a client never has to
            // decide what to print when the short name was left empty.
            shortName: $team->getShortName(),
            slug: $team->getSlug(),
            createdAt: $team->getCreatedAt(),
            squadSize: $squadSize,
            seasonsPlayed: $seasonsPlayed,
            latestSeason: $latestSeason,
        );
    }
}
