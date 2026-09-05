<?php

declare(strict_types=1);

namespace App\Domain\Club;

use App\Domain\Standings\StandingsCalculator;
use App\Domain\Standings\StandingsTable;
use App\Dto\Output\ClubSeasonRow;
use App\Dto\Output\RosterEntryResource;
use App\Dto\Output\SeasonRefResource;
use App\Dto\Output\TeamProfileResource;
use App\Dto\Output\TeamResource;
use App\Entity\SeasonTeam;
use App\Entity\Team;
use App\Repository\FixtureRepository;
use App\Repository\RosterEntryRepository;
use App\Repository\SeasonTeamRepository;

/**
 * One club's page: who is in the squad now, and how every season it has played went.
 *
 * Composed rather than forwarded — the registrations, the squad, the results and the league
 * position come from four different places and none of them can answer for another. That is
 * the same argument {@see StandingsCalculator} makes for existing at all.
 *
 * Six queries for a club with any number of seasons. The one thing that would not stay
 * constant is the league position, which needs the whole table for a season computed, so it
 * is asked for once — for the season currently being played — and left null for history.
 * A club's own record does not need the table and is reported for every season.
 */
final readonly class ClubProfile
{
    public function __construct(
        private SeasonTeamRepository $seasonTeams,
        private RosterEntryRepository $rosterEntries,
        private FixtureRepository $fixtures,
        private StandingsCalculator $standings,
    ) {
    }

    public function forTeam(Team $team): TeamProfileResource
    {
        $teamId = (int) $team->getId();
        $registrations = $this->seasonTeams->registrationsForTeams([$teamId])[$teamId] ?? [];
        $latest = $registrations[0] ?? null;

        $squadSizes = $this->seasonTeams->squadSizesFor(array_map(
            static fn (SeasonTeam $registration): int => (int) $registration->getId(),
            $registrations,
        ));

        $aggregates = $this->fixtures->seasonAggregatesForTeam($team);
        $position = null === $latest ? [] : $this->positions($latest);

        return new TeamProfileResource(
            team: TeamResource::fromEntity(
                $team,
                squadSize: null === $latest ? 0 : ($squadSizes[(int) $latest->getId()] ?? 0),
                seasonsPlayed: \count($registrations),
                latestSeason: null === $latest ? null : SeasonRefResource::fromEntity($latest->getSeason()),
            ),
            latestSeasonId: null === $latest ? null : (int) $latest->getSeason()->getId(),
            squad: null === $latest ? [] : array_map(
                RosterEntryResource::fromEntity(...),
                $this->rosterEntries->findForSquad($latest),
            ),
            seasons: array_map(
                function (SeasonTeam $registration) use ($team, $squadSizes, $aggregates, $position): ClubSeasonRow {
                    $season = $registration->getSeason();
                    $league = $season->getLeague();
                    $seasonId = (int) $season->getId();

                    // Reusing the table builder for a single club rather than adding the
                    // points up here: three for a win is a rule, and a rule stated twice is a
                    // rule that will disagree with itself. The position it invents for a
                    // one-row table is meaningless and is thrown away.
                    $row = StandingsTable::build(
                        [(int) $team->getId() => $team->getName()],
                        $aggregates[$seasonId] ?? [],
                    )[0];

                    return new ClubSeasonRow(
                        seasonId: $seasonId,
                        seasonName: $season->getName(),
                        leagueId: (int) $league->getId(),
                        leagueName: $league->getName(),
                        startDate: $season->getStartDate(),
                        squadSize: $squadSizes[(int) $registration->getId()] ?? 0,
                        played: $row->played,
                        won: $row->won,
                        drawn: $row->drawn,
                        lost: $row->lost,
                        goalsFor: $row->goalsFor,
                        goalsAgainst: $row->goalsAgainst,
                        goalDifference: $row->goalDifference,
                        points: $row->points,
                        position: $position[$seasonId] ?? null,
                    );
                },
                $registrations,
            ),
        );
    }

    /**
     * Where this club stands, for the one season worth computing a whole table for.
     *
     * @return array<int, int> season id => position
     */
    private function positions(SeasonTeam $latest): array
    {
        $seasonId = (int) $latest->getSeason()->getId();
        $teamId = (int) $latest->getTeam()->getId();

        foreach ($this->standings->forSeason($latest->getSeason()) as $row) {
            if ($row->teamId === $teamId) {
                return [$seasonId => $row->position];
            }
        }

        return [];
    }
}
