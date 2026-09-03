<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\OrganizationRole;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMemberRoleRequest
{
    public function __construct(
        #[Assert\Choice(callback: [OrganizationRole::class, 'assignable'], message: 'Choose a valid role.')]
        public OrganizationRole $role = OrganizationRole::Member,
    ) {
    }
}
