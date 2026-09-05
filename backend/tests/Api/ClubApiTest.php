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
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a club row and a club page say about themselves.
 *
 * The results are played through the real endpoints rather than written into the score
 * columns, for the reason StandingsApiTest gives: a test that sets `home_score` by hand
 * proves the arithmetic and nothing else.
 */
final class ClubApiTest extends ApiTestCase
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

        foreach (['Alfa', 'Beta', 'Gamma'] as $name) {
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

    public function testAClubRowCarriesItsSquadAndItsSeasons(): void
    {
        $this->request('GET', $this->clubsUri(), null, $this->token);

        self::assertResponseIsSuccessful();

        $rows = $this->byName($this->jsonList());

        self::assertSame(1, $rows['Alfa']['squad_size']);
        self::assertSame(1, $rows['Alfa']['seasons_played']);
        self::assertSame('2026', $rows['Alfa']['latest_season']['name']);
        self::assertSame(
            $this->season->getLeague()->getId(),
            $rows['Alfa']['latest_season']['league_id'],
            'the league id is on the row because every path to a season goes through it',
        );
    }

    /**
     * A club nobody has entered for a season is not a club with a missing squad — it is a
     * club with no squad, and the difference matters to whatever renders the column.
     */
    public function testAClubThatHasNeverBeenEnteredSaysSo(): void
    {
        TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Delta']);

        $this->request('GET', $this->clubsUri(), null, $this->token);

        $rows = $this->byName($this->jsonList());

        self::assertSame(0, $rows['Delta']['squad_size']);
        self::assertSame(0, $rows['Delta']['seasons_played']);
        self::assertNull($rows['Delta']['latest_season']);
    }

    /**
     * The whole point of fetching the aggregates a page at a time.
     *
     * The number itself is not asserted — it will change the next time somebody adds a column
     * — but its independence from the number of rows is the property worth defending, and it
     * is the one a per-row lookup silently breaks.
     */
    public function testListingClubsCostsTheSameForThreeAsForTen(): void
    {
        $withThree = $this->queriesForListingClubs(3);
        $withTen = $this->queriesForListingClubs(10);

        self::assertSame(
            $withThree,
            $withTen,
            \sprintf('Three clubs took %d queries, ten took %d.', $withThree, $withTen),
        );
    }

    /**
     * A single club has to answer the same thing as its row in the list, or `GET /teams/5`
     * quietly reports a club that plainly has a squad as having none.
     */
    public function testOneClubAnswersTheSameThingAsItsRow(): void
    {
        $this->request('GET', $this->clubsUri().'/'.$this->clubs['Alfa']->getId(), null, $this->token);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['squad_size']);
        self::assertSame(1, $this->json()['seasons_played']);
    }

    public function testTheProfileCarriesTheSquadAndTheSeasonsRecord(): void
    {
        $this->playOut('Alfa', 'Beta', 2, 1);
        $this->playOut('Gamma', 'Alfa', 1, 1);

        $this->request('GET', $this->profileUri('Alfa'), null, $this->token);

        self::assertResponseIsSuccessful();

        $profile = $this->json();

        self::assertSame('Alfa', $profile['team']['name']);
        self::assertSame($this->season->getId(), $profile['latest_season_id']);
        self::assertSame(['Striker Alfa'], array_column($profile['squad'], 'player_name'));

        self::assertCount(1, $profile['seasons']);
        $season = $profile['seasons'][0];

        self::assertSame('2026', $season['season_name']);
        self::assertSame([2, 1, 1, 0, 3, 2, 1, 4], [
            $season['played'], $season['won'], $season['drawn'], $season['lost'],
            $season['goals_for'], $season['goals_against'], $season['goal_difference'],
            $season['points'],
        ]);
    }

    /**
     * The cheapest way to trust a second implementation of the same arithmetic: ask the one
     * that was already there. A club's own aggregate and its line of the league table are
     * computed by different queries and must not be able to disagree.
     */
    public function testTheProfilesRecordAgreesWithTheLeagueTable(): void
    {
        $this->playOut('Alfa', 'Beta', 3, 0);
        $this->playOut('Beta', 'Gamma', 1, 2);
        $this->playOut('Gamma', 'Alfa', 0, 0);

        $this->request('GET', $this->seasonUri().'/standings', null, $this->token);
        $table = [];

        foreach ($this->jsonList() as $row) {
            $table[$row['team_name']] = $row;
        }

        foreach (['Alfa', 'Beta', 'Gamma'] as $name) {
            $this->request('GET', $this->profileUri($name), null, $this->token);
            $season = $this->json()['seasons'][0];

            foreach (['played', 'won', 'drawn', 'lost', 'goals_for', 'goals_against', 'goal_difference', 'points'] as $field) {
                self::assertSame($table[$name][$field], $season[$field], "$name: $field");
            }

            self::assertSame($table[$name]['position'], $season['position'], "$name: position");
        }
    }

    /**
     * A match still being played has a score, and that score is on the match page — but the
     * club's record waits for full time, exactly as the league table does.
     */
    public function testAMatchInProgressDoesNotCountTowardsTheRecord(): void
    {
        $this->leaveRunning('Alfa', 'Beta', 3, 0);

        $this->request('GET', $this->profileUri('Alfa'), null, $this->token);

        self::assertSame(0, $this->json()['seasons'][0]['played']);
        self::assertSame(0, $this->json()['seasons'][0]['points']);
    }

    public function testAClubOfAnotherOrganizationIsNotReachable(): void
    {
        $stranger = UserFactory::createOne();
        $theirs = OrganizationFactory::createOne(['createdBy' => $stranger]);
        $theirClub = TeamFactory::createOne(['organization' => $theirs]);

        $this->request(
            'GET',
            \sprintf(
                '/api/v1/organizations/%d/teams/%d/profile',
                $this->organization->getId(),
                $theirClub->getId(),
            ),
            null,
            $this->token,
        );

        // Not 403: ids are unique table-wide, so the organization is part of the lookup and
        // a club outside it simply is not there.
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function queriesForListingClubs(int $clubs): int
    {
        $league = LeagueFactory::createOne(['organization' => $this->organization]);
        $season = SeasonFactory::createOne(['league' => $league]);

        for ($i = 0; $i < $clubs; ++$i) {
            $team = TeamFactory::createOne(['organization' => $this->organization]);
            $seasonTeam = SeasonTeamFactory::createOne(['season' => $season, 'team' => $team]);
            // Squads too, so the counts really have something to count.
            RosterEntryFactory::createMany(2, ['seasonTeam' => $seasonTeam]);
        }

        // The profiler is the supported way to count queries since DBAL 4 dropped DebugStack.
        $this->client->enableProfiler();
        $this->request('GET', $this->clubsUri().'?page=1&page_size='.$clubs, null, $this->token);

        self::assertResponseIsSuccessful();

        $profile = $this->client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile, 'the profiler has to be collecting for this to mean anything');

        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, array<string, mixed>>
     */
    private function byName(array $rows): array
    {
        $byName = [];

        foreach ($rows as $row) {
            /** @var string $name */
            $name = $row['name'];
            $byName[$name] = $row;
        }

        return $byName;
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

        foreach ([[$home, $homeGoals], [$away, $awayGoals]] as [$club, $goals]) {
            for ($i = 1; $i <= $goals; ++$i) {
                $this->request('POST', $this->matchUri($fixture).'/events', [
                    'type' => 'GOAL',
                    'minute' => $club === $home ? $i * 7 : 45 + $i * 7,
                    'team_id' => $this->clubs[$club]->getId(),
                    'player_id' => $this->players[$club]->getId(),
                ], $this->token);

                self::assertResponseIsSuccessful();
            }
        }

        return $fixture;
    }

    /**
     * Every request in a functional test reboots the kernel, and with it the entity manager,
     * so the entities are looked up again by the ids that survive the reboot.
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

    private function clubsUri(): string
    {
        return '/api/v1/organizations/'.$this->organization->getId().'/teams';
    }

    private function profileUri(string $club): string
    {
        return $this->clubsUri().'/'.$this->clubs[$club]->getId().'/profile';
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
