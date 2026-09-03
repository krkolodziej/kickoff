<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Team;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Team>
 */
final class TeamFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Team::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'organization' => OrganizationFactory::new(),
            'name' => self::faker()->unique()->city(),
            'slug' => self::faker()->unique()->slug(2),
        ];
    }
}
