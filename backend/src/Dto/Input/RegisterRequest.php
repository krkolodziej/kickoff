<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The registration payload.
 *
 * This is not an entity, and that is the point: user input hydrates a DTO, the DTO is
 * validated, and only then does application code decide what to write. An entity populated
 * straight from a request body is one forgotten field away from letting a caller set
 * anything the mapping exposes.
 *
 * The property names are camelCase; the wire format is snake_case. The serializer's name
 * converter (configured once in framework.yaml) bridges the two, so `password_confirm` in
 * the JSON body lands on `$passwordConfirm` here.
 *
 * UniqueEntity works on a plain object as long as it is told which entity to look in. It is
 * a courtesy, not a guarantee: two simultaneous registrations can both pass it, which is why
 * there is also a unique index on `users.email`.
 */
#[UniqueEntity(
    fields: ['email'],
    entityClass: User::class,
    message: 'An account with this email already exists.',
)]
final class RegisterRequest
{
    #[Assert\NotBlank(message: 'Enter your email address.')]
    #[Assert\Email(message: 'Enter a valid email address.')]
    #[Assert\Length(max: 180)]
    public string $email;

    public function __construct(
        string $email = '',
        #[Assert\NotBlank(message: 'Choose a password.')]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'Use at least {{ limit }} characters.')]
        public string $password = '',
        #[Assert\Expression(
            expression: 'value === this.password',
            message: 'The two passwords do not match.',
        )]
        public string $passwordConfirm = '',
        #[Assert\Length(max: 100)]
        public string $firstName = '',
        #[Assert\Length(max: 100)]
        public string $lastName = '',
    ) {
        // Normalised here rather than in the controller, so that UniqueEntity above compares
        // the same value the entity will end up storing.
        $this->email = User::normaliseEmail($email);
    }
}
