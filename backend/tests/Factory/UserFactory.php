<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const DEFAULT_PASSWORD = 'correct-horse-battery';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'password' => self::DEFAULT_PASSWORD,
        ];
    }

    /**
     * `password` above is the plain text a test wants to log in with. Hashing it here — after
     * the entity exists, because the hasher needs the user to pick its algorithm — keeps the
     * factory's interface honest: callers say what the password *is*, not what it hashes to.
     */
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
        });
    }
}
