<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeasonTeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A club taking part in one season.
 *
 * This is the row that keeps `Team` free of any league: the club is a permanent fact about
 * the organization, and *this* is the fact about a particular season. Promotion is a new
 * SeasonTeam, not a different club.
 *
 * It is also what a squad hangs off, because a squad is only meaningful for one club in one
 * season — the same player can be in two of them, a year apart, wearing different numbers.
 */
#[ORM\Entity(repositoryClass: SeasonTeamRepository::class)]
#[ORM\Table(name: 'season_teams')]
#[ORM\UniqueConstraint(name: 'uniq_season_team', columns: ['season_id', 'team_id'])]
#[ORM\HasLifecycleCallbacks]
class SeasonTeam
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'seasonTeams')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Season $season;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Team $team;

    /** @var Collection<int, RosterEntry> */
    #[ORM\OneToMany(targetEntity: RosterEntry::class, mappedBy: 'seasonTeam', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rosterEntries;

    public function __construct(Season $season, Team $team)
    {
        $this->season = $season;
        $this->team = $team;
        $this->rosterEntries = new ArrayCollection();

        $season->addSeasonTeam($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeason(): Season
    {
        return $this->season;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    /**
     * @return Collection<int, RosterEntry>
     */
    public function getRosterEntries(): Collection
    {
        return $this->rosterEntries;
    }

    public function addRosterEntry(RosterEntry $entry): static
    {
        if (!$this->rosterEntries->contains($entry)) {
            $this->rosterEntries->add($entry);
        }

        return $this;
    }
}
