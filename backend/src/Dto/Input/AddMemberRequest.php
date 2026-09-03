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
        /*
         * A string, not an OrganizationRole, and that is deliberate.
         *
         * Typed as the enum, an unrecognised value is rejected by the serializer before
         * validation ever runs, and the caller is told "This value should be of type
         * int|string." — accurate, and no help at all to somebody filling in a form. As a
         * string it reaches our own Choice constraint, so every bad role — unknown, or
         * simply not assignable, like OWNER — comes back with the same sentence.
         */
        #[Assert\Choice(
            callback: [OrganizationRole::class, 'assignableValues'],
            message: 'Choose a valid role.',
        )]
        public string $role = 'MEMBER',
    ) {
    }

    /**
     * Safe because validation has already run: MapRequestPayload validates before the
     * controller is called, so an unassignable value never reaches this method.
     */
    public function role(): OrganizationRole
    {
        return OrganizationRole::from($this->role);
    }
}
