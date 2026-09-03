<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class TeamRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Give the club a name.')]
        #[Assert\Length(min: 2, max: 150, minMessage: 'Use at least {{ limit }} characters.')]
        public string $name = '',
        #[Assert\Length(max: 64)]
        #[Assert\Regex(
            pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            message: 'Use lowercase letters, numbers and single hyphens.',
        )]
        public ?string $slug = null,
        /* What fits in a league table column. Falls back to the full name when left empty. */
        #[Assert\Length(max: 32, maxMessage: 'A short name has to fit a table column: {{ limit }} characters.')]
        public string $shortName = '',
    ) {
    }
}
