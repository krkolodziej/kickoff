<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\PlayerPosition;
use Symfony\Component\Validator\Constraints as Assert;

final class RosterEntryRequest
{
    public function __construct(
        #[Assert\Positive(message: 'Choose a player.')]
        public int $playerId = 0,
        /*
         * Bounded at 99 because that is what fits on a shirt. Nullable, because a squad is
         * routinely entered before the numbers are handed out.
         */
        #[Assert\Range(min: 1, max: 99, notInRangeMessage: 'A shirt number is between {{ min }} and {{ max }}.')]
        public ?int $shirtNumber = null,
        /* A string on the wire for the same reason as the organization role — see NOTES §2.7. */
        #[Assert\Choice(
            callback: [PlayerPosition::class, 'values'],
            message: 'Choose one of: goalkeeper, defender, midfielder, forward.',
        )]
        public ?string $position = null,
        public bool $captain = false,
    ) {
    }

    public function position(): ?PlayerPosition
    {
        return null === $this->position ? null : PlayerPosition::from($this->position);
    }
}
