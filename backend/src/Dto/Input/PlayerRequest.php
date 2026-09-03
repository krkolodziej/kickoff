<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class PlayerRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Enter a first name.')]
        #[Assert\Length(max: 100)]
        public string $firstName = '',
        #[Assert\NotBlank(message: 'Enter a last name.')]
        #[Assert\Length(max: 100)]
        public string $lastName = '',
        /*
         * Nullable, and bounded on both sides. LessThan('today') because a player cannot be
         * born tomorrow, and a floor because a mistyped year is otherwise accepted in
         * silence and only shows up years later in an age column.
         */
        #[Assert\LessThan('today', message: 'A date of birth cannot be in the future.')]
        #[Assert\GreaterThan('-120 years', message: 'Check the year — that is over 120 years ago.')]
        public ?\DateTimeImmutable $dateOfBirth = null,
    ) {
    }
}
