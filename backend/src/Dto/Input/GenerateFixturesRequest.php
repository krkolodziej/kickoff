<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class GenerateFixturesRequest
{
    public function __construct(
        /** Everyone plays everyone twice, home and away. The usual arrangement. */
        public bool $doubleRound = true,
        /** Defaults to the season's own start date. */
        public ?\DateTimeImmutable $firstRoundOn = null,
        #[Assert\Range(min: 1, max: 60, notInRangeMessage: 'Space the rounds between {{ min }} and {{ max }} days apart.')]
        public int $daysBetweenRounds = 7,
    ) {
    }
}
