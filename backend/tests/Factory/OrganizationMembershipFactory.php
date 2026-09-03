<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\OrganizationMembership;
use App\Entity\OrganizationRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<OrganizationMembership>
 */
final class OrganizationMembershipFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return OrganizationMembership::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'organization' => OrganizationFactory::new(),
            'user' => UserFactory::new(),
            'role' => OrganizationRole::Member,
        ];
    }
}
