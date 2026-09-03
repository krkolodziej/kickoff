<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LeagueRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A competition that runs season after season: "Liga Okręgowa", "Klasa A".
 *
 * The slug is unique *per organization*, not globally. Two associations may both run a
 * league of the same name, and neither has a claim on the word.
 */
#[ORM\Entity(repositoryClass: LeagueRepository::class)]
#[ORM\Table(name: 'leagues')]
#[ORM\UniqueConstraint(name: 'uniq_league_organization_slug', columns: ['organization_id', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class League
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

    #[ORM\Column(type: 'text')]
    private string $description = '';

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = trim($description);

        return $this;
    }
}
