<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Creating or renaming an organization.
 *
 * `slug` is optional: leaving it out derives one from the name and makes it unique by
 * suffixing. Someone who cares about the URL can still say what it should be.
 */
final class OrganizationRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Give the organization a name.')]
        #[Assert\Length(min: 2, max: 150, minMessage: 'Use at least {{ limit }} characters.')]
        public string $name = '',
        #[Assert\Length(max: 64)]
        #[Assert\Regex(
            pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            message: 'Use lowercase letters, numbers and single hyphens.',
        )]
        public ?string $slug = null,
    ) {
    }
}
