<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\SeasonTeam;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SeasonTeam>
 */
final class SeasonTeamFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SeasonTeam::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'season' => SeasonFactory::new(),
            'team' => TeamFactory::new(),
        ];
    }
}
