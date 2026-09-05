<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Player;
use App\Entity\RosterEntry;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class PlayerResource
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        /* A date, not an instant — see SeasonResource for what that costs otherwise. */
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public ?\DateTimeImmutable $dateOfBirth,
        public \DateTimeImmutable $createdAt,
        /**
         * Everything below is annotated onto the person from somewhere else, which is why it
         * defaults rather than being required: a player who has just been registered has no
         * squad and no matches, and that is not a missing value.
         *
         * The defaults are also what let the callers that genuinely have nothing to add —
         * `POST /players` is the only one — keep passing an entity and nothing else.
         */
        public ?PlayerSquadResource $currentSquad = null,
        /**
         * Derived, not stored. A date of birth is data; an age is what a table column wants,
         * and computing it here gives the whole application one definition of "today".
         */
        public ?int $age = null,
        public int $goals = 0,
        public int $yellowCards = 0,
        public int $redCards = 0,
    ) {
    }

    public static function fromEntity(
        Player $player,
        ?RosterEntry $currentSquad = null,
        ?PlayerTotals $totals = null,
    ): self {
        return new self(
            id: (int) $player->getId(),
            organizationId: (int) $player->getOrganization()->getId(),
            firstName: $player->getFirstName(),
            lastName: $player->getLastName(),
            fullName: $player->getFullName(),
            dateOfBirth: $player->getDateOfBirth(),
            createdAt: $player->getCreatedAt(),
            currentSquad: null === $currentSquad ? null : PlayerSquadResource::fromEntity($currentSquad),
            age: self::age($player->getDateOfBirth()),
            goals: $totals->goals ?? 0,
            yellowCards: $totals->yellowCards ?? 0,
            redCards: $totals->redCards ?? 0,
        );
    }

    /**
     * Whole years, against today.
     *
     * `PlayerRequest` already refuses a date in the future, so this cannot come back negative
     * for anything the API accepted. `diff()` on two dates handles leap years, which is the
     * entire reason not to do this with arithmetic on the year.
     */
    private static function age(?\DateTimeImmutable $dateOfBirth): ?int
    {
        if (null === $dateOfBirth) {
            return null;
        }

        return $dateOfBirth->diff(new \DateTimeImmutable('today'))->y;
    }
}
