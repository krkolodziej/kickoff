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
    ) {
    }

    public static function fromEntity(Organization $organization, OrganizationRole $myRole, int $memberCount): self
    {
        return new self(
            id: (int) $organization->getId(),
            name: $organization->getName(),
            slug: $organization->getSlug(),
            myRole: $myRole,
            memberCount: $memberCount,
            createdAt: $organization->getCreatedAt(),
        );
    }
}
