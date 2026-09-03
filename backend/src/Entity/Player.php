<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A person, registered with the organization rather than with a club.
 *
 * Which club they play for, in which season, wearing which number, is a squad entry — and
 * it changes. Keeping the person separate is what lets a career be followed across
 * transfers instead of being re-typed as a new player every August.
 */
#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[ORM\Table(name: 'players')]
#[ORM\Index(name: 'idx_player_organization_last_name', columns: ['organization_id', 'last_name'])]
#[ORM\HasLifecycleCallbacks]
class Player
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    /**
     * Nullable, and it has to be. Amateur leagues routinely register a player before anyone
     * has checked a date of birth, and a schema that refuses the entry until then simply
     * gets a placeholder date instead — which is worse, because it looks like data.
     */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateOfBirth = null;

    public function __construct(Organization $organization, string $firstName, string $lastName)
    {
        $this->organization = $organization;
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
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

    public function getDateOfBirth(): ?\DateTimeImmutable
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeImmutable $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }
}
