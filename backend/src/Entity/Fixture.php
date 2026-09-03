<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FixtureRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A scheduled meeting: who plays whom, in which round, on which leg.
 *
 * The unique index is on (season, home, away) rather than on the unordered pair, and that is
 * the point of a double round robin: Stal-Resovia and Resovia-Stal are two different
 * fixtures, and both are supposed to exist.
 */
#[ORM\Entity(repositoryClass: FixtureRepository::class)]
#[ORM\Table(name: 'fixtures')]
#[ORM\UniqueConstraint(name: 'uniq_fixture_season_direction', columns: ['season_id', 'home_team_id', 'away_team_id'])]
#[ORM\Index(name: 'idx_fixture_season_round', columns: ['season_id', 'round_number'])]
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
}
