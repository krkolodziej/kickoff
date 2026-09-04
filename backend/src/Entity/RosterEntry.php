<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RosterEntryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One player in one club's squad for one season.
 *
 * The shirt number is unique within the squad — and the plain unique index is enough for
 * that even though the column is nullable, because SQL treats NULLs as distinct: any number
 * of unnumbered players coexist, while two number nines do not. (The application this
 * replaces added a conditional index for the same rule; on MariaDB there are no partial
 * indexes, and here there is no need for one.)
 */
#[ORM\Entity(repositoryClass: RosterEntryRepository::class)]
#[ORM\Table(name: 'roster_entries')]
#[ORM\UniqueConstraint(name: 'uniq_roster_squad_player', columns: ['season_team_id', 'player_id'])]
#[ORM\UniqueConstraint(name: 'uniq_roster_squad_shirt', columns: ['season_team_id', 'shirt_number'])]
// A partial unique index: unique across the squad, but only over the rows where `captain`
// is true. It is what turns "at most one captain" from a promise the application makes
// into a fact about the schema — and it is the one concrete thing PostgreSQL buys here
// that MariaDB could not express at all.
#[ORM\UniqueConstraint(name: 'uniq_roster_one_captain', columns: ['season_team_id'], options: ['where' => 'captain'])]
#[ORM\HasLifecycleCallbacks]
class RosterEntry
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rosterEntries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SeasonTeam $seasonTeam;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $player;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $shirtNumber = null;

    #[ORM\Column(length: 16, nullable: true, enumType: PlayerPosition::class)]
    private ?PlayerPosition $position = null;

    /**
     * At most one per squad, guaranteed by the partial unique index declared above.
     *
     * SquadManager still demotes the previous captain rather than letting a write fail —
     * refusing would make an operator hunt for whoever currently holds the armband — but the
     * rule no longer depends on that being remembered.
     */
    #[ORM\Column]
    private bool $captain = false;

    public function __construct(SeasonTeam $seasonTeam, Player $player)
    {
        $this->seasonTeam = $seasonTeam;
        $this->player = $player;

        $seasonTeam->addRosterEntry($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeasonTeam(): SeasonTeam
    {
        return $this->seasonTeam;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getShirtNumber(): ?int
    {
        return $this->shirtNumber;
    }

    public function setShirtNumber(?int $shirtNumber): static
    {
        $this->shirtNumber = $shirtNumber;

        return $this;
    }

    public function getPosition(): ?PlayerPosition
    {
        return $this->position;
    }

    public function setPosition(?PlayerPosition $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isCaptain(): bool
    {
        return $this->captain;
    }

    public function setCaptain(bool $captain): static
    {
        $this->captain = $captain;

        return $this;
    }
}
