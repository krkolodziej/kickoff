<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Player;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Player>
 */
final class PlayerFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Player::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'organization' => OrganizationFactory::new(),
            'firstName' => self::faker()->firstNameMale(),
            'lastName' => self::faker()->lastName(),
        ];
    }
}
