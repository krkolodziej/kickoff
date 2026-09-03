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
    ) {
    }

    public static function fromEntity(League $league): self
    {
        return new self(
            id: (int) $league->getId(),
            organizationId: (int) $league->getOrganization()->getId(),
            name: $league->getName(),
            slug: $league->getSlug(),
            description: $league->getDescription(),
            createdAt: $league->getCreatedAt(),
        );
    }
}
