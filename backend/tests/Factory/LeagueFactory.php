<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\League;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<League>
 */
final class LeagueFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return League::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'organization' => OrganizationFactory::new(),
            'name' => self::faker()->unique()->words(2, true),
            'slug' => self::faker()->unique()->slug(2),
        ];
    }
}
