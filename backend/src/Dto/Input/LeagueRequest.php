<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class LeagueRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Give the league a name.')]
        #[Assert\Length(min: 2, max: 150, minMessage: 'Use at least {{ limit }} characters.')]
        public string $name = '',
        #[Assert\Length(max: 64)]
        #[Assert\Regex(
            pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            message: 'Use lowercase letters, numbers and single hyphens.',
        )]
        public ?string $slug = null,
        #[Assert\Length(max: 2000, maxMessage: 'Keep the description under {{ limit }} characters.')]
        public string $description = '',
    ) {
    }
}
