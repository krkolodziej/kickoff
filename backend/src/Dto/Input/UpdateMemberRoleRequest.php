<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\OrganizationRole;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMemberRoleRequest
{
    public function __construct(
        /* A string for the same reason as AddMemberRequest::$role. */
        #[Assert\Choice(
            callback: [OrganizationRole::class, 'assignableValues'],
            message: 'Choose a valid role.',
        )]
        public string $role = 'MEMBER',
    ) {
    }

    public function role(): OrganizationRole
    {
        return OrganizationRole::from($this->role);
    }
}
