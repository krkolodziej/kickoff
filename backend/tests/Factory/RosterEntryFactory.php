<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\RosterEntry;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<RosterEntry>
 */
final class RosterEntryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return RosterEntry::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'seasonTeam' => SeasonTeamFactory::new(),
            'player' => PlayerFactory::new(),
        ];
    }
}
