<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Validator\SeasonName;
use Symfony\Component\Validator\Constraints as Assert;

final class SeasonRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name the season.')]
        #[SeasonName]
        public string $name = '',
        #[Assert\NotNull(message: 'Say when the season starts.')]
        public ?\DateTimeImmutable $startDate = null,
        /*
         * `propertyPath` compares against another field of the same object, which is exactly
         * this rule and needs no custom constraint. Nullable because the last round is
         * usually not known when the season is created.
         */
        #[Assert\GreaterThanOrEqual(
            propertyPath: 'startDate',
            message: 'A season cannot end before it starts.',
        )]
        public ?\DateTimeImmutable $endDate = null,
    ) {
    }
}
