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
    ) {
    }

    public static function fromEntity(Team $team): self
    {
        return new self(
            id: (int) $team->getId(),
            organizationId: (int) $team->getOrganization()->getId(),
            name: $team->getName(),
            // The getter already falls back to the full name, so a client never has to
            // decide what to print when the short name was left empty.
            shortName: $team->getShortName(),
            slug: $team->getSlug(),
            createdAt: $team->getCreatedAt(),
        );
    }
}
