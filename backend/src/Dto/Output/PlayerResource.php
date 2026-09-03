<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Player;

final readonly class PlayerResource
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?\DateTimeImmutable $dateOfBirth,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(Player $player): self
    {
        return new self(
            id: (int) $player->getId(),
            organizationId: (int) $player->getOrganization()->getId(),
            firstName: $player->getFirstName(),
            lastName: $player->getLastName(),
            fullName: $player->getFullName(),
            dateOfBirth: $player->getDateOfBirth(),
            createdAt: $player->getCreatedAt(),
        );
    }
}
