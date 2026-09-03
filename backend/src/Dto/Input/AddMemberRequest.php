<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\OrganizationRole;
use Symfony\Component\Validator\Constraints as Assert;

final class AddMemberRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Enter the email address of the person to add.')]
        #[Assert\Email(message: 'Enter a valid email address.')]
        public string $email = '',

        /**
         * Constrained to the assignable roles, so `OWNER` in a request body is a validation
         * error rather than a way to mint a second owner. The enum is the whole allow-list;
         * there is no second place to keep in step with it.
         */
        #[Assert\Choice(callback: [OrganizationRole::class, 'assignable'], message: 'Choose a valid role.')]
        public OrganizationRole $role = OrganizationRole::Member,
    ) {
    }
}
