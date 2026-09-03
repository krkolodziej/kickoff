<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\User;

/**
 * What the API says a user is.
 *
 * Serialising the entity directly would work today and leak tomorrow: the first time
 * someone adds a column, it appears in the response without anyone deciding that it should.
 * An explicit output DTO makes the payload a choice rather than a side effect — and it means
 * `password` cannot be exposed by accident, because it is not here to expose.
 */
final readonly class UserResource
{
    public function __construct(
        public int $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $fullName,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: (int) $user->getId(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            fullName: $user->getFullName(),
        );
    }

    /**
     * The success handler builds a plain array, so the resource has to be able to flatten
     * itself outside of a controller's serializer call.
     *
     * @return array{id: int, email: string, first_name: string, last_name: string, full_name: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
        ];
    }
}
