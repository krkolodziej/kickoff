<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The account. Identified by e-mail, never by a separate username.
 *
 * Note what is *not* here: there is no "is this person an administrator" flag. Authority in
 * Kickoff is granted per organization, so it lives on OrganizationMembership. `getRoles()`
 * returns the same thing for everybody, and the interesting decisions are made by voters.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Stored already lower-cased. The column collation (utf8mb4_unicode_ci) is
     * case-insensitive too, so the unique index holds either way — but normalising on the
     * way in means the value we compare in PHP is the value that is in the database.
     */
    #[ORM\Column(length: 180)]
    private string $email;

    /** The bcrypt/argon hash, never the password. */
    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(length: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    private string $lastName = '';

    public function __construct(string $email)
    {
        $this->email = self::normaliseEmail($email);
    }

    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = self::normaliseEmail($email);

        return $this;
    }

    /**
     * What Symfony calls the user by. Because the provider is configured with
     * `property: email`, this is the value it looks up, and the value Lexik puts in the
     * token's `username` claim.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    /**
     * Called after authentication to drop anything sensitive that is not needed again.
     * The hash is needed on every request (the provider refreshes the user), so nothing
     * is erased here — but the method must exist, and saying so out loud is better than
     * an empty body with no explanation.
     */
    public function eraseCredentials(): void
    {
    }
}
