<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FixtureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A meeting: who plays whom, in which round — and, once it kicks off, what happened in it.
 *
 * There is no separate `Match` entity, for two reasons.
 *
 * The blunt one: **`Match` is a reserved word in PHP 8**. `class Match {}` is a parse error,
 * because `match` is an expression keyword and class names are case-insensitive. Any such
 * entity would have to be called something it is not.
 *
 * The better one: the split would be an artefact. A fixture and a match are the same event at
 * two moments — before and after the whistle — and modelling them separately buys a join on
 * every read, plus an operator step ("create the match") that means nothing to anybody
 * running a league. The application this replaces had a one-to-one between them, and every
 * query paid for it.
 *
 * The unique index is on (season, home, away) rather than on the unordered pair, which is the
 * point of a double round robin: Stal-Resovia and Resovia-Stal are two different fixtures and
 * both are supposed to exist.
 */
#[ORM\Entity(repositoryClass: FixtureRepository::class)]
#[ORM\Table(name: 'fixtures')]
#[ORM\UniqueConstraint(name: 'uniq_fixture_season_direction', columns: ['season_id', 'home_team_id', 'away_team_id'])]
#[ORM\Index(name: 'idx_fixture_season_round', columns: ['season_id', 'round_number'])]
#[ORM\Index(name: 'idx_fixture_season_status', columns: ['season_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class Fixture
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Season $season;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Team $homeTeam;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Team $awayTeam;

    #[ORM\Column(type: 'smallint')]
    private int $roundNumber;

    /** 1 or 2. The second leg is the same pairing with the sides swapped. */
    #[ORM\Column(type: 'smallint')]
    private int $leg;

    /**
     * An instant, not a date — a kick-off happens at a time, in a timezone, and the whole
     * point of a calendar is being able to say 15:00 rather than "that Saturday".
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true, options: ['comment' => 'UTC'])]
    private ?\DateTimeImmutable $kickOffAt = null;

    #[ORM\Column(length: 16, enumType: MatchStatus::class, options: ['default' => 'SCHEDULED'])]
    private MatchStatus $status = MatchStatus::Scheduled;

    /**
     * Never written by hand. Every change to these two goes through the same transaction that
     * records the goal causing it, so the score cannot disagree with its own history.
     */
    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $homeScore = 0;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $awayScore = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, options: ['comment' => 'UTC'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, options: ['comment' => 'UTC'])]
    private ?\DateTimeImmutable $finishedAt = null;

    /** @var Collection<int, MatchEvent> */
    #[ORM\OneToMany(targetEntity: MatchEvent::class, mappedBy: 'fixture', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $events;

    public function __construct(
        Season $season,
        Team $homeTeam,
        Team $awayTeam,
        int $roundNumber,
        int $leg,
    ) {
        $this->season = $season;
        $this->homeTeam = $homeTeam;
        $this->awayTeam = $awayTeam;
        $this->roundNumber = $roundNumber;
        $this->leg = $leg;
        $this->events = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeason(): Season
    {
        return $this->season;
    }

    public function getHomeTeam(): Team
    {
        return $this->homeTeam;
    }

    public function getAwayTeam(): Team
    {
        return $this->awayTeam;
    }

    public function getRoundNumber(): int
    {
        return $this->roundNumber;
    }

    public function getLeg(): int
    {
        return $this->leg;
    }

    public function getKickOffAt(): ?\DateTimeImmutable
    {
        return $this->kickOffAt;
    }

    public function setKickOffAt(?\DateTimeImmutable $kickOffAt): static
    {
        $this->kickOffAt = $kickOffAt;

        return $this;
    }

    public function getStatus(): MatchStatus
    {
        return $this->status;
    }

    public function setStatus(MatchStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isLive(): bool
    {
        return MatchStatus::Live === $this->status;
    }

    public function getHomeScore(): int
    {
        return $this->homeScore;
    }

    public function getAwayScore(): int
    {
        return $this->awayScore;
    }

    /**
     * The only way a score moves. Kept on the entity so the rule "a goal is worth one" lives
     * beside the fields it changes, rather than as arithmetic scattered through a service.
     */
    public function recordGoalFor(Team $team): static
    {
        if ($this->isHomeTeam($team)) {
            ++$this->homeScore;
        } else {
            ++$this->awayScore;
        }

        return $this;
    }

    public function isHomeTeam(Team $team): bool
    {
        return $this->homeTeam->getId() === $team->getId();
    }

    public function involves(Team $team): bool
    {
        return $this->isHomeTeam($team) || $this->awayTeam->getId() === $team->getId();
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    /**
     * @return Collection<int, MatchEvent>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(MatchEvent $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
        }

        return $this;
    }
}
