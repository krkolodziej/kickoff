<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\OrganizationMembership;
use App\Entity\OrganizationRole;

final readonly class MembershipResource
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $email,
        public string $fullName,
        public OrganizationRole $role,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(OrganizationMembership $membership): self
    {
        $user = $membership->getUser();

        return new self(
            id: (int) $membership->getId(),
            userId: (int) $user->getId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            role: $membership->getRole(),
            createdAt: $membership->getCreatedAt(),
        );
    }
}
