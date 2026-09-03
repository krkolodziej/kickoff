<?php

declare(strict_types=1);

namespace App\Domain\Fixture;

use App\Entity\Fixture;
use App\Entity\Season;
use App\Entity\Team;
use App\Exception\SchedulingException;
use App\Repository\FixtureRepository;
use App\Repository\SeasonTeamRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Turns the clubs registered for a season into rows.
 *
 * This is the half the scheduler refuses to know about: entities, transactions, locks. The
 * split means the interesting logic is testable without a database and the persistence is
 * testable without arithmetic.
 */
final class FixtureGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoundRobinScheduler $scheduler,
        private readonly SeasonTeamRepository $seasonTeams,
        private readonly FixtureRepository $fixtures,
    ) {
    }

    /**
     * @return list<Fixture>
     */
    public function generate(
        Season $season,
        bool $doubleRound,
        ?\DateTimeImmutable $firstRoundOn,
        int $daysBetweenRounds,
    ): array {
        return $this->entityManager->wrapInTransaction(
            function () use ($season, $doubleRound, $firstRoundOn, $daysBetweenRounds): array {
                // The lock, and the reason it is here: two managers clicking Generate at the
                // same moment would both find no fixtures, both build a calendar and both
                // insert it. The unique index would reject the second insert *mid-way*,
                // leaving half a calendar behind. SELECT … FOR UPDATE makes the second
                // request wait, find the fixtures the first one wrote, and refuse cleanly.
                $locked = $this->entityManager->find(
                    Season::class,
                    $season->getId(),
                    LockMode::PESSIMISTIC_WRITE,
                );

                if (null === $locked) {
                    throw SchedulingException::alreadyGenerated();
                }

                if ($this->fixtures->seasonHasFixtures($locked)) {
                    throw SchedulingException::alreadyGenerated();
                }

                $registered = $this->seasonTeams->findForSeason($locked);

                /** @var array<int, Team> $teamsById */
                $teamsById = [];

                foreach ($registered as $row) {
                    $team = $row['seasonTeam']->getTeam();
                    $teamsById[(int) $team->getId()] = $team;
                }

                $pairings = $this->scheduler->schedule(array_keys($teamsById), $doubleRound);
                $kickOffs = $this->kickOffSchedule($locked, $firstRoundOn, $daysBetweenRounds);

                $created = [];

                foreach ($pairings as $pairing) {
                    $fixture = new Fixture(
                        $locked,
                        $teamsById[$pairing->homeTeamId],
                        $teamsById[$pairing->awayTeamId],
                        $pairing->roundNumber,
                        $pairing->leg,
                    );

                    $fixture->setKickOffAt($kickOffs[$pairing->roundNumber] ?? null);

                    $this->entityManager->persist($fixture);
                    $created[] = $fixture;
                }

                return $created;
            },
        );
    }

    public function clear(Season $season): int
    {
        return $this->fixtures->deleteForSeason($season);
    }

    /**
     * One kick-off per round, spaced evenly from a starting date.
     *
     * A calendar of 132 fixtures with no dates on it is a list, not a calendar — so rounds
     * get a Saturday afternoon each by default, and whoever runs the league can move
     * individual games afterwards.
     *
     * @return array<int, \DateTimeImmutable>
     */
    private function kickOffSchedule(
        Season $season,
        ?\DateTimeImmutable $firstRoundOn,
        int $daysBetweenRounds,
    ): array {
        $start = $firstRoundOn ?? $season->getStartDate();
        $days = max(1, $daysBetweenRounds);

        $schedule = [];

        // Generous upper bound: more rounds than any amateur league will ever run, and the
        // extra keys cost nothing because only the ones asked for are read.
        for ($round = 1; $round <= 200; ++$round) {
            $schedule[$round] = $start
                ->setTime(15, 0)
                ->modify(\sprintf('+%d days', ($round - 1) * $days));
        }

        return $schedule;
    }
}
