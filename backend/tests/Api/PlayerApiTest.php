<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Fixture;
use App\Entity\Organization;
use App\Entity\Season;
use App\Entity\Team;
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

final class PlayerApiTest extends ApiTestCase
{
    public function testAPlayerCanBeRegisteredWithoutADateOfBirth(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $this->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/players',
            ['first_name' => 'Jan', 'last_name' => 'Kowalski'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNull($this->json()['date_of_birth']);
        self::assertSame('Jan Kowalski', $this->json()['full_name']);

        // Nobody has picked him for anything yet, which is an answer rather than a gap.
        self::assertNull($this->json()['current_squad']);
        self::assertNull($this->json()['age']);
        self::assertSame(0, $this->json()['goals']);
    }

    public function testADateOfBirthIsEmittedAsADate(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $this->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/players',
            ['first_name' => 'Jan', 'last_name' => 'Kowalski', 'date_of_birth' => '1998-04-12'],
            $this->signIn($owner),
        );

        self::assertSame('1998-04-12', $this->json()['date_of_birth']);
    }

    public function testADateOfBirthInTheFutureIsRefused(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $this->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/players',
            [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'date_of_birth' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
            ],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('date_of_birth', $this->json()['fields']);
    }

    public function testAPlayerOfAnotherOrganizationIsNotReachable(): void
    {
        $owner = UserFactory::createOne();
        $mine = OrganizationFactory::createOne(['createdBy' => $owner]);
        $elsewhere = PlayerFactory::createOne();

        $this->request(
            'PATCH',
            '/api/v1/organizations/'.$mine->getId().'/players/'.$elsewhere->getId(),
            ['first_name' => 'Hijacked', 'last_name' => 'Player'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * The rule the whole "current squad" idea rests on, and the one most likely to be got
     * wrong: the rows arrive newest-season-first and the first one wins. It is invisible in
     * the demonstration data, which has a single season.
     */
    public function testAPlayerCarriesTheSquadOfTheirMostRecentSeason(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);
        $player = PlayerFactory::createOne(['organization' => $organization]);

        foreach ([['2025', 'Old Club', 4], ['2026', 'New Club', 7]] as [$name, $club, $shirt]) {
            $season = SeasonFactory::createOne([
                'league' => $league,
                'name' => $name,
                'startDate' => new \DateTimeImmutable($name.'-03-01'),
            ]);

            RosterEntryFactory::createOne([
                'seasonTeam' => SeasonTeamFactory::createOne([
                    'season' => $season,
                    'team' => TeamFactory::createOne(['organization' => $organization, 'name' => $club]),
                ]),
                'player' => $player,
                'shirtNumber' => $shirt,
            ]);
        }

        $this->request(
            'GET',
            '/api/v1/organizations/'.$organization->getId().'/players/'.$player->getId(),
            null,
            $this->signIn($owner),
        );

        self::assertResponseIsSuccessful();

        $squad = $this->json()['current_squad'];
        self::assertSame('New Club', $squad['team_name']);
        self::assertSame('2026', $squad['season_name']);
        self::assertSame(7, $squad['shirt_number']);
    }

    /**
     * The number is not asserted — it moves whenever a column is added — but its independence
     * from the number of rows is, because that is what a per-row lookup silently breaks.
     */
    public function testListingPlayersCostsTheSameForThreeAsForTen(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $token = $this->signIn($owner);

        $withThree = $this->queriesForListingPlayers($organization, $token, 3);
        $withTen = $this->queriesForListingPlayers($organization, $token, 10);

        self::assertSame(
            $withThree,
            $withTen,
            \sprintf('Three players took %d queries, ten took %d.', $withThree, $withTen),
        );
    }

    /**
     * The aggregates have to be fetched for the page that was asked for, not for the first
     * one — an easy thing to get wrong when the ids are collected in the wrong place.
     */
    public function testASecondPageCarriesItsOwnSquads(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $season = SeasonFactory::createOne([
            'league' => LeagueFactory::createOne(['organization' => $organization]),
        ]);
        $seasonTeam = SeasonTeamFactory::createOne([
            'season' => $season,
            'team' => TeamFactory::createOne(['organization' => $organization, 'name' => 'Alfa']),
        ]);

        // Surnames chosen so that the default ordering by last name is known.
        foreach (['Aaa', 'Bbb', 'Ccc', 'Ddd'] as $index => $lastName) {
            RosterEntryFactory::createOne([
                'seasonTeam' => $seasonTeam,
                'player' => PlayerFactory::createOne([
                    'organization' => $organization,
                    'lastName' => $lastName,
                ]),
                'shirtNumber' => $index + 1,
            ]);
        }

        $this->request(
            'GET',
            '/api/v1/organizations/'.$organization->getId().'/players?page=2&page_size=2',
            null,
            $this->signIn($owner),
        );

        self::assertResponseIsSuccessful();

        $rows = $this->json()['results'];
        self::assertSame(['Ccc', 'Ddd'], array_column($rows, 'last_name'));
        self::assertSame([3, 4], array_map(
            static fn (array $row): int => $row['current_squad']['shirt_number'],
            $rows,
        ));
    }

    public function testTheProfileCountsGoalsPerSeasonAndIgnoresCancelledMatches(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);
        $season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);
        $token = $this->signIn($owner);

        $home = TeamFactory::createOne(['organization' => $organization, 'name' => 'Alfa']);
        $away = TeamFactory::createOne(['organization' => $organization, 'name' => 'Beta']);
        $player = PlayerFactory::createOne(['organization' => $organization, 'lastName' => 'Striker']);

        RosterEntryFactory::createOne([
            'seasonTeam' => SeasonTeamFactory::createOne(['season' => $season, 'team' => $home]),
            'player' => $player,
            'shirtNumber' => 9,
        ]);
        SeasonTeamFactory::createOne(['season' => $season, 'team' => $away]);

        $seasonUri = \sprintf(
            '/api/v1/organizations/%d/leagues/%d/seasons/%d',
            $organization->getId(),
            $league->getId(),
            $season->getId(),
        );

        $counted = $this->fixture($season->getId(), $home->getId(), $away->getId());
        $this->request('POST', $seasonUri.'/fixtures/'.$counted->getId().'/start', null, $token);
        $this->goal($seasonUri, $counted, $home->getId(), $player->getId(), 10, $token);
        $this->goal($seasonUri, $counted, $home->getId(), $player->getId(), 20, $token);
        $this->request('POST', $seasonUri.'/fixtures/'.$counted->getId().'/finish', null, $token);

        // A goal in a match that was then called off did not happen. The return leg, because
        // a season may only hold one fixture per pair per direction.
        $abandoned = $this->fixture($season->getId(), $away->getId(), $home->getId());
        $this->request('POST', $seasonUri.'/fixtures/'.$abandoned->getId().'/start', null, $token);
        $this->goal($seasonUri, $abandoned, $home->getId(), $player->getId(), 30, $token);
        $this->request('POST', $seasonUri.'/fixtures/'.$abandoned->getId().'/cancel', null, $token);

        $this->request(
            'GET',
            '/api/v1/organizations/'.$organization->getId().'/players/'.$player->getId().'/profile',
            null,
            $token,
        );

        self::assertResponseIsSuccessful();

        $profile = $this->json();

        self::assertSame(2, $profile['player']['goals'], 'the cancelled match is not counted');
        self::assertCount(1, $profile['seasons']);
        self::assertSame('2026', $profile['seasons'][0]['season_name']);
        self::assertSame('Alfa', $profile['seasons'][0]['team_name']);
        self::assertSame(9, $profile['seasons'][0]['shirt_number']);
        self::assertSame(2, $profile['seasons'][0]['goals']);
    }

    public function testTheProfileOfAPlayerInAnotherOrganizationIsNotReachable(): void
    {
        $owner = UserFactory::createOne();
        $mine = OrganizationFactory::createOne(['createdBy' => $owner]);
        $elsewhere = PlayerFactory::createOne();

        $this->request(
            'GET',
            '/api/v1/organizations/'.$mine->getId().'/players/'.$elsewhere->getId().'/profile',
            null,
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function queriesForListingPlayers(Organization $organization, string $token, int $players): int
    {
        $seasonTeam = SeasonTeamFactory::createOne();

        for ($i = 0; $i < $players; ++$i) {
            RosterEntryFactory::createOne([
                'seasonTeam' => $seasonTeam,
                'player' => PlayerFactory::createOne(['organization' => $organization]),
                'shirtNumber' => $i + 1,
            ]);
        }

        $this->client->enableProfiler();
        $this->request(
            'GET',
            \sprintf(
                '/api/v1/organizations/%d/players?page=1&page_size=%d',
                $organization->getId(),
                $players,
            ),
            null,
            $token,
        );

        self::assertResponseIsSuccessful();

        $profile = $this->client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile, 'the profiler has to be collecting for this to mean anything');

        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    /**
     * The kernel reboots between requests and takes the entity manager with it, so the rows
     * are looked up again by the ids that survive.
     */
    private function fixture(?int $seasonId, ?int $homeId, ?int $awayId): Fixture
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $season = $entityManager->find(Season::class, $seasonId);
        $home = $entityManager->find(Team::class, $homeId);
        $away = $entityManager->find(Team::class, $awayId);

        self::assertNotNull($season);
        self::assertNotNull($home);
        self::assertNotNull($away);

        $fixture = new Fixture($season, $home, $away, 1, 1);
        $entityManager->persist($fixture);
        $entityManager->flush();

        return $fixture;
    }

    private function goal(
        string $seasonUri,
        Fixture $fixture,
        ?int $teamId,
        ?int $playerId,
        int $minute,
        string $token,
    ): void {
        $this->request('POST', $seasonUri.'/fixtures/'.$fixture->getId().'/events', [
            'type' => 'GOAL',
            'minute' => $minute,
            'team_id' => $teamId,
            'player_id' => $playerId,
        ], $token);

        self::assertResponseIsSuccessful();
    }
}
