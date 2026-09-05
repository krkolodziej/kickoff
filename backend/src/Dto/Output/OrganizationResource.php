<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Organization;
use App\Entity\OrganizationRole;

/**
 * `myRole` is the caller's own authority, folded into the resource.
 *
 * Without it the client would have to fetch the member list just to decide whether to draw
 * an "Edit" button — on every screen. It is annotated from the same membership row that
 * proved access, so it costs nothing extra.
 *
 * The three counts are there for a different reason: an organization card that says only how
 * many people are in it tells a reader nothing about what is inside. They are batched, never
 * fetched per row — see {@see \App\Repository\OrganizationRepository::countsFor()}.
 */
final readonly class OrganizationResource
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public OrganizationRole $myRole,
        public int $memberCount,
        public \DateTimeImmutable $createdAt,
        public int $leagueCount = 0,
        public int $teamCount = 0,
        public int $playerCount = 0,
    ) {
    }

    /**
     * @param array{leagues: int, teams: int, players: int}|null $counts
     */
    public static function fromEntity(
        Organization $organization,
        OrganizationRole $myRole,
        int $memberCount,
        ?array $counts = null,
    ): self {
        return new self(
            id: (int) $organization->getId(),
            name: $organization->getName(),
            slug: $organization->getSlug(),
            myRole: $myRole,
            memberCount: $memberCount,
            createdAt: $organization->getCreatedAt(),
            leagueCount: $counts['leagues'] ?? 0,
            teamCount: $counts['teams'] ?? 0,
            playerCount: $counts['players'] ?? 0,
        );
    }
}
