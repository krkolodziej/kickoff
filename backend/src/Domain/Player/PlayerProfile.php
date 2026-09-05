<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Dto\Output\PlayerProfileResource;
use App\Dto\Output\PlayerResource;
use App\Dto\Output\PlayerSeasonRow;
use App\Dto\Output\PlayerTotals;
use App\Entity\Player;
use App\Entity\RosterEntry;
use App\Repository\MatchEventRepository;
use App\Repository\RosterEntryRepository;

/**
 * One player's career, assembled from the two places it is recorded.
 *
 * Where somebody was registered lives on roster entries; what they did lives on match events.
 * Neither half can produce the other, and joining them is the only work this class does — but
 * it is work with a rule in it (which totals belong to which registration), and a rule that
 * lives in a controller is a rule that ends up implemented twice.
 *
 * Two queries, whatever the length of the career.
 */
final readonly class PlayerProfile
{
    public function __construct(
        private RosterEntryRepository $rosterEntries,
        private MatchEventRepository $matchEvents,
    ) {
    }

    public function forPlayer(Player $player): PlayerProfileResource
    {
        $registrations = $this->rosterEntries->findForPlayer($player);
        $bySeason = $this->matchEvents->totalsForPlayerBySeason($player);

        return new PlayerProfileResource(
            // The career total is the sum of the seasons rather than a third query for the
            // same rows. Two numbers derived from one query cannot disagree with each other,
            // which is worth more here than the query it saves.
            player: PlayerResource::fromEntity($player, $registrations[0] ?? null, $this->career($player, $bySeason)),
            seasons: array_map(
                static function (RosterEntry $entry) use ($bySeason): PlayerSeasonRow {
                    $seasonTeam = $entry->getSeasonTeam();
                    $season = $seasonTeam->getSeason();
                    $league = $season->getLeague();
                    $team = $seasonTeam->getTeam();

                    // Keyed by season *and* club: a player who moves mid-season has two
                    // registrations in one season, and season alone would hand both of them
                    // the same totals.
                    $did = $bySeason[MatchEventRepository::seasonTeamKey(
                        (int) $season->getId(),
                        (int) $team->getId(),
                    )] ?? null;

                    return new PlayerSeasonRow(
                        seasonId: (int) $season->getId(),
                        seasonName: $season->getName(),
                        leagueId: (int) $league->getId(),
                        leagueName: $league->getName(),
                        teamId: (int) $team->getId(),
                        teamName: $team->getName(),
                        shirtNumber: $entry->getShirtNumber(),
                        position: $entry->getPosition(),
                        captain: $entry->isCaptain(),
                        goals: $did->goals ?? 0,
                        yellowCards: $did->yellowCards ?? 0,
                        redCards: $did->redCards ?? 0,
                    );
                },
                $registrations,
            ),
        );
    }

    /**
     * @param array<string, PlayerTotals> $bySeason
     */
    private function career(Player $player, array $bySeason): PlayerTotals
    {
        $goals = 0;
        $yellow = 0;
        $red = 0;

        foreach ($bySeason as $totals) {
            $goals += $totals->goals;
            $yellow += $totals->yellowCards;
            $red += $totals->redCards;
        }

        return new PlayerTotals((int) $player->getId(), $goals, $yellow, $red);
    }
}
