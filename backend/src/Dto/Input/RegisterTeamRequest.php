<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterTeamRequest
{
    public function __construct(
        #[Assert\Positive(message: 'Choose a club.')]
        public int $teamId = 0,
    ) {
    }
}
