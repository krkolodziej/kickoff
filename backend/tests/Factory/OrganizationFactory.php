<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\OrganizationRole;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Organization>
 */
final class OrganizationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Organization::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        $name = self::faker()->unique()->company();

        return [
            'name' => $name,
            'slug' => self::faker()->unique()->slug(2),
            'createdBy' => UserFactory::new(),
        ];
    }

    /**
     * An organization with no owner is a row nobody has authority over, so the factory
     * never produces one: the creator is made owner as soon as the entity exists.
     */
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Organization $organization): void {
            new OrganizationMembership($organization, $organization->getCreatedBy(), OrganizationRole::Owner);
        });
    }

    public function ownedBy(User $user): static
    {
        return $this->with(['createdBy' => $user]);
    }
}
