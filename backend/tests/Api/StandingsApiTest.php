<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Fixture;
use App\Entity\Organization;
use App\Entity\Player;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\PlayerFactory;
use App\Tests\Factory\RosterEntryFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\SeasonTeamFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The table and the statistics are played into existence through the real endpoints rather
 * than written straight into the database. A test that sets `home_score` by hand proves the
 * arithmetic and nothing else; this one would also catch a goal that stops moving the score,
 * a finish that forgets to record the result, or a status filter applied to the wrong column.
 */
final class StandingsApiTest extends ApiTestCase
{
    private User $owner;
    private Organization $organization;
    private Season $season;
    private string $token;

    /** @var array<string, Team> */
    private array $clubs = [];

    /** @var array<string, Player> */
    private array $players = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = UserFactory::createOne();
        $this->organization = OrganizationFactory::createOne(['createdBy' => $this->owner]);
        $league = LeagueFactory::createOne(['organization' => $this->organization]);
        $this->season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);

        foreach (['Alfa', 'Beta', 'Gamma', 'Delta'] as $name) {
            $team = TeamFactory::createOne(['organization' => $this->organization, 'name' => $name]);
            $squad = SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $team]);
            $player = PlayerFactory::createOne([
                'organization' => $this->organization,
                'firstName' => 'Striker',
                'lastName' => $name,
            ]);

            RosterEntryFactory::createOne([
                'seasonTeam' => $squad,
                'player' => $player,
                'shirtNumber' => 9,
            ]);

            $this->clubs[$name] = $team;
            $this->players[$name] = $player;
        }

        $this->token = $this->signIn($this->owner);
    }

    public function testTheTableIsBuiltFromFinishedMatches(): void
    {
        $this->playOut('Alfa', 'Beta', 2, 1);
        $this->playOut('Beta', 'Gamma', 1, 1);
        $this->playOut('Alfa', 'Gamma', 0, 0);

        $rows = $this->standings();

        self::assertSame(['Alfa', 'Gamma', 'Beta', 'Delta'], array_column($rows, 'team_name'));
        self::assertSame([1, 2, 3, 4], array_column($rows, 'position'));

        [$alfa, $gamma, $beta, $delta] = $rows;

        self::assertSame([2, 1, 1, 0, 2, 1, 1, 4], [
            $alfa['played'], $alfa['won'], $alfa['drawn'], $alfa['lost'],
            $alfa['goals_for'], $alfa['goals_against'], $alfa['goal_difference'], $alfa['points'],
        ]);

        self::assertSame(2, $gamma['points'], 'two draws');
        self::assertSame(1, $beta['points'], 'a defeat and a draw');

        // A club that has not played yet is still in its own league.
        self::assertSame(0, $delta['played']);
        self::assertSame(0, $delta['points']);
    }

    /**
     * Points are awarded at full time. A match being played has a score, and that score is
     * visible on the match page — but it is not in the table until the whistle goes.
     */
    public function testAMatchInProgressDoesNotCountTowardsTheTable(): void
    {
        $this->playOut('Alfa', 'Beta', 1, 0);
        $this->leaveRunning('Gamma', 'Delta', 3, 0);

        $rows = $this->byClub($this->standings());

        self::assertSame(0, $rows['Gamma']['played']);
        self::assertSame(0, $rows['Gamma']['points']);
        self::assertSame(0, $rows['Gamma']['goals_for']);
        self::assertSame(3, $rows['Alfa']['points']);
    }

    /**
     * The other half of the same rule, and the reason it is worth stating: a goal scored in a
     * match still being played is a goal. The scorer list moves while you watch it and the
     * table does not, exactly as a real competition behaves.
     */
    public function testGoalsInAMatchInProgressDoCountTowardsThePlayerStatistics(): void
    {
        $this->playOut('Alfa', 'Beta', 2, 1);
        $this->leaveRunning('Gamma', 'Delta', 3, 0);

        $rows = $this->statistics();

        self::assertSame('Gamma', $rows[0]['last_name'], 'three live goals beat two finished ones');
        self::assertSame(3, $rows[0]['goals']);
        self::assertSame(2, $rows[1]['goals']);
    }

    /**
     * A cancelled match did not happen, whatever was recorded before it was called off.
     */
    public function testACancelledMatchCountsForNeitherTheTableNorTheStatistics(): void
    {
        $fixture = $this->fixture('Delta', 'Alfa');
        $this->request('POST', $this->matchUri($fixture).'/start', null, $this->token);
        $this->score($fixture, 'Delta', 1);
        $this->request('POST', $this->matchUri($fixture).'/cancel', null, $this->token);

        $rows = $this->byClub($this->standings());
        self::assertSame(0, $rows['Delta']['played']);
        self::assertSame(0, $rows['Alfa']['played']);

        self::assertSame(
            [],
            array_filter($this->statistics(), static fn (array $r): bool => 'Delta' === $r['last_name']),
            'a goal in a match that was called off is not a goal in anybody\'s season',
        );
    }

    public function testAPlayerWithNothingToShowIsNotInTheList(): void
    {
        $this->playOut('Alfa', 'Beta', 1, 0);

        $names = array_column($this->statistics(), 'last_name');

        self::assertContains('Alfa', $names);
        self::assertNotContains('Gamma', $names, 'the list is what players did, not who exists');
    }

    public function testCardsAreCountedSeparatelyFromGoals(): void
    {
        $fixture = $this->fixture('Alfa', 'Beta');
        $this->request('POST', $this->matchUri($fixture).'/start', null, $this->token);
        $this->event($fixture, 'YELLOW_CARD', 'Beta', 12);
        $this->event($fixture, 'YELLOW_CARD', 'Beta', 40);
        $this->event($fixture, 'RED_CARD', 'Beta', 55);
        $this->request('POST', $this->matchUri($fixture).'/finish', null, $this->token);

        $beta = array_values(array_filter(
            $this->statistics(),
            static fn (array $r): bool => 'Beta' === $r['last_name'],
        ))[0];

        self::assertSame(0, $beta['goals']);
        self::assertSame(2, $beta['yellow_cards']);
        self::assertSame(1, $beta['red_cards']);
    }

    public function testAStrangerCannotReadEitherReport(): void
    {
        $stranger = UserFactory::createOne();
        $token = $this->signIn($stranger);

        $this->request('GET', $this->seasonUri().'/standings', null, $token);
        self::assertResponseStatusCodeSame(404, 'not 403: a stranger is not told the season exists');

        $this->request('GET', $this->seasonUri().'/statistics', null, $token);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function standings(): array
    {
        $this->request('GET', $this->seasonUri().'/standings', null, $this->token);
        self::assertResponseIsSuccessful();

        return $this->jsonList();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statistics(): array
    {
        $this->request('GET', $this->seasonUri().'/statistics', null, $this->token);
        self::assertResponseIsSuccessful();

        return $this->jsonList();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, array<string, mixed>>
     */
    private function byClub(array $rows): array
    {
        $byClub = [];

        foreach ($rows as $row) {
            /** @var string $name */
            $name = $row['team_name'];
            $byClub[$name] = $row;
        }

        return $byClub;
    }

    private function playOut(string $home, string $away, int $homeGoals, int $awayGoals): Fixture
    {
        $fixture = $this->leaveRunning($home, $away, $homeGoals, $awayGoals);

        $this->request('POST', $this->matchUri($fixture).'/finish', null, $this->token);
        self::assertResponseIsSuccessful();

        return $fixture;
    }

    private function leaveRunning(string $home, string $away, int $homeGoals, int $awayGoals): Fixture
    {
        $fixture = $this->fixture($home, $away);

        $this->request('POST', $this->matchUri($fixture).'/start', null, $this->token);
        self::assertResponseIsSuccessful();

        $this->score($fixture, $home, $homeGoals);
        $this->score($fixture, $away, $awayGoals);

        return $fixture;
    }

    private function score(Fixture $fixture, string $club, int $goals): void
    {
        for ($i = 1; $i <= $goals; ++$i) {
            $this->event($fixture, 'GOAL', $club, $i * 7);
        }
    }

    private function event(Fixture $fixture, string $type, string $club, int $minute): void
    {
        $this->request('POST', $this->matchUri($fixture).'/events', [
            'type' => $type,
            'minute' => $minute,
            'team_id' => $this->clubs[$club]->getId(),
            'player_id' => $this->players[$club]->getId(),
        ], $this->token);

        self::assertResponseIsSuccessful();
    }

    /**
     * Every request in a functional test reboots the kernel, and with it the entity manager.
     * The objects created in `setUp` belong to the one that existed then, so handing them to
     * a later manager makes it see three brand new rows and refuse to save any of them. The
     * ids are the part that survives a reboot, so the entities are looked up again here.
     */
    private function fixture(string $home, string $away): Fixture
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $season = $entityManager->find(Season::class, $this->season->getId());
        $homeTeam = $entityManager->find(Team::class, $this->clubs[$home]->getId());
        $awayTeam = $entityManager->find(Team::class, $this->clubs[$away]->getId());

        self::assertNotNull($season);
        self::assertNotNull($homeTeam);
        self::assertNotNull($awayTeam);

        $fixture = new Fixture($season, $homeTeam, $awayTeam, 1, 1);
        $entityManager->persist($fixture);
        $entityManager->flush();

        return $fixture;
    }

    private function seasonUri(): string
    {
        return \sprintf(
            '/api/v1/organizations/%d/leagues/%d/seasons/%d',
            $this->organization->getId(),
            $this->season->getLeague()->getId(),
            $this->season->getId(),
        );
    }

    private function matchUri(Fixture $fixture): string
    {
        return $this->seasonUri().'/fixtures/'.$fixture->getId();
    }
}
