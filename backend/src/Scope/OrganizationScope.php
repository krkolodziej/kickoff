<?php

declare(strict_types=1);

namespace App\Scope;

use App\Entity\Organization;
use App\Entity\OrganizationRole;

final readonly class OrganizationScope implements ScopeInterface
{
    public function __construct(
        private Organization $organization,
        private OrganizationRole $role,
    ) {
    }

    public function organization(): Organization
    {
        return $this->organization;
    }

    public function role(): OrganizationRole
    {
        return $this->role;
    }
}
