<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A club, registered once with the organization and reused across seasons and leagues.
 *
 * Clubs deliberately do not belong to a league. A club that is promoted, relegated or moved
 * between competitions is the same club — modelling it per league would fork its identity
 * every time it moved, and its history with it.
 */
#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'teams')]
#[ORM\UniqueConstraint(name: 'uniq_team_organization_slug', columns: ['organization_id', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class Team
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 64)]
    private string $slug;

    /** Optional, and short: "Stal", "Resovia" — what a league table has room to print. */
    #[ORM\Column(length: 32)]
    private string $shortName = '';

    public function __construct(Organization $organization, string $name, string $slug)
    {
        $this->organization = $organization;
        $this->name = $name;
        $this->slug = $slug;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getShortName(): string
    {
        return '' === $this->shortName ? $this->name : $this->shortName;
    }

    public function setShortName(string $shortName): static
    {
        $this->shortName = trim($shortName);

        return $this;
    }
}
