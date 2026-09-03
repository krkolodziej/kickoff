<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * One edition of a league: "2026/27".
 *
 * Everything that changes from year to year hangs off here — which clubs took part, who was
 * in each squad, the calendar, the results. The league is the continuity; the season is the
 * thing that actually happened.
 */
#[ORM\Entity(repositoryClass: SeasonRepository::class)]
#[ORM\Table(name: 'seasons')]
#[ORM\UniqueConstraint(name: 'uniq_season_league_name', columns: ['league_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
class Season
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private League $league;

    /** "2026" for a spring-to-autumn season, "2026/27" for one that crosses the new year. */
    #[ORM\Column(length: 32)]
    private string $name;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    /**
     * Nullable: a season is usually created before anyone knows when the last round will be
     * played, and pretending to know is worse than admitting not to.
     */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    /** @var Collection<int, SeasonTeam> */
    #[ORM\OneToMany(targetEntity: SeasonTeam::class, mappedBy: 'season', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $seasonTeams;

    public function __construct(League $league, string $name, \DateTimeImmutable $startDate)
    {
        $this->league = $league;
        $this->name = $name;
        $this->startDate = $startDate;
        $this->seasonTeams = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLeague(): League
    {
        return $this->league;
    }

    public function getOrganization(): Organization
    {
        return $this->league->getOrganization();
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

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * @return Collection<int, SeasonTeam>
     */
    public function getSeasonTeams(): Collection
    {
        return $this->seasonTeams;
    }

    public function addSeasonTeam(SeasonTeam $seasonTeam): static
    {
        if (!$this->seasonTeams->contains($seasonTeam)) {
            $this->seasonTeams->add($seasonTeam);
        }

        return $this;
    }
}
