<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MatchEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Something that happened during a match, at a minute.
 *
 * **Append-only.** There is no endpoint that edits or deletes one, and that is deliberate: the
 * score is derived from these rows, so an editable event means a score that can silently stop
 * matching its own history. A mistake is corrected by recording the truth, the way a referee's
 * notebook works.
 *
 * `PROTECT`-style restraint on the club and the players: deleting a player who has scored
 * would erase the goal from the record.
 */
#[ORM\Entity(repositoryClass: MatchEventRepository::class)]
#[ORM\Table(name: 'match_events')]
#[ORM\Index(name: 'idx_event_fixture_type', columns: ['fixture_id', 'type'])]
#[ORM\HasLifecycleCallbacks]
class MatchEvent
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Fixture $fixture;

    #[ORM\Column(length: 16, enumType: MatchEventType::class)]
    private MatchEventType $type;

    /** 1 to 180: ninety minutes, extra time, and generous stoppage. */
    #[ORM\Column(type: 'smallint')]
    private int $minute;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Team $team;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Player $player;

    /** The player coming on. Only ever set for a substitution. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Player $relatedPlayer = null;

    public function __construct(
        Fixture $fixture,
        MatchEventType $type,
        int $minute,
        Team $team,
        Player $player,
    ) {
        $this->fixture = $fixture;
        $this->type = $type;
        $this->minute = $minute;
        $this->team = $team;
        $this->player = $player;

        $fixture->addEvent($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFixture(): Fixture
    {
        return $this->fixture;
    }

    public function getType(): MatchEventType
    {
        return $this->type;
    }

    public function getMinute(): int
    {
        return $this->minute;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getRelatedPlayer(): ?Player
    {
        return $this->relatedPlayer;
    }

    public function setRelatedPlayer(?Player $relatedPlayer): static
    {
        $this->relatedPlayer = $relatedPlayer;

        return $this;
    }
}
