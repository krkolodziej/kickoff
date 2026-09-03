<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Season;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Season>
 */
final class SeasonFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Season::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'league' => LeagueFactory::new(),
            'name' => (string) self::faker()->unique()->numberBetween(2000, 2099),
            'startDate' => \DateTimeImmutable::createFromInterface(self::faker()->dateTimeThisDecade()),
        ];
    }
}
