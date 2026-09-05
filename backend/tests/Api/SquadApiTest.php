<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Organization;
use App\Entity\Season;
use App\Entity\SeasonTeam;
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
use Symfony\Component\HttpFoundation\Response;

final class SquadApiTest extends ApiTestCase
{
    private User $owner;
    private Organization $organization;
    private Season $season;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = UserFactory::createOne();
        $this->organization = OrganizationFactory::createOne(['createdBy' => $this->owner]);
        $league = LeagueFactory::createOne(['organization' => $this->organization]);
        $this->season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);
        $this->token = $this->signIn($this->owner);
    }

    public function testAClubIsRegisteredForASeason(): void
    {
        $team = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Stal']);

        $this->request('POST', $this->teamsUri(), ['team_id' => $team->getId()], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('Stal', $this->json()['team_name']);
        self::assertSame(0, $this->json()['squad_size']);
    }

    /**
     * Ids are sequential and the endpoint is otherwise perfectly legitimate, so without this
     * check an admin could register any club whose id they could guess.
     */
    public function testAClubFromAnotherOrganizationCannotBeRegistered(): void
    {
        $elsewhere = TeamFactory::createOne();

        $this->request('POST', $this->teamsUri(), ['team_id' => $elsewhere->getId()], $this->token);

        // 404 rather than a rule violation: from inside this organization, that club does
        // not exist at all.
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAClubCannotBeRegisteredTwice(): void
    {
        $team = TeamFactory::createOne(['organization' => $this->organization]);
        SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $team]);

        $this->request('POST', $this->teamsUri(), ['team_id' => $team->getId()], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('already_registered', $this->json()['code']);
    }

    public function testAPlayerJoinsASquadWithANumberAndAPosition(): void
    {
        $seasonTeam = $this->registeredClub();
        $player = PlayerFactory::createOne(['organization' => $this->organization, 'firstName' => 'Jan', 'lastName' => 'Kowalski']);

        $this->request(
            'POST',
            $this->rosterUri($seasonTeam),
            ['player_id' => $player->getId(), 'shirt_number' => 9, 'position' => 'FORWARD'],
            $this->token,
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->json();
        self::assertSame(9, $body['shirt_number']);
        self::assertSame('FORWARD', $body['position']);
        self::assertSame('Jan Kowalski', $body['player_name']);
        self::assertFalse($body['captain']);
    }

    public function testASquadEntryNeedsNeitherANumberNorAPosition(): void
    {
        $seasonTeam = $this->registeredClub();
        $player = PlayerFactory::createOne(['organization' => $this->organization]);

        $this->request('POST', $this->rosterUri($seasonTeam), ['player_id' => $player->getId()], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNull($this->json()['shirt_number']);
        self::assertNull($this->json()['position']);
    }

    /**
     * The unique index would also stop this — as a 500. The point of checking first is the
     * message, and the field it lands on.
     */
    public function testATakenShirtNumberIsRefusedOnTheField(): void
    {
        $seasonTeam = $this->registeredClub();
        $first = PlayerFactory::createOne(['organization' => $this->organization]);
        $second = PlayerFactory::createOne(['organization' => $this->organization]);

        $this->request('POST', $this->rosterUri($seasonTeam), ['player_id' => $first->getId(), 'shirt_number' => 9], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->request('POST', $this->rosterUri($seasonTeam), ['player_id' => $second->getId(), 'shirt_number' => 9], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('squad_rule_violated', $this->json()['code']);
        self::assertStringContainsString('9', $this->json()['fields']['shirt_number'][0]);
    }

    /**
     * SQL treats NULLs as distinct, so the plain unique index on (squad, number) already
     * allows any number of unnumbered players while refusing two number nines. No partial
     * index needed — which matters, because MariaDB does not have them.
     */
    public function testAnyNumberOfPlayersMayHaveNoNumber(): void
    {
        $seasonTeam = $this->registeredClub();

        foreach (range(1, 3) as $ignored) {
            $player = PlayerFactory::createOne(['organization' => $this->organization]);
            $this->request('POST', $this->rosterUri($seasonTeam), ['player_id' => $player->getId()], $this->token);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        $this->request('GET', $this->rosterUri($seasonTeam), null, $this->token);
        self::assertCount(3, $this->jsonList());
    }

    public function testTheSameNumberMayBeWornInTwoDifferentSquads(): void
    {
        $first = $this->registeredClub();
        $second = $this->registeredClub();

        foreach ([$first, $second] as $squad) {
            $player = PlayerFactory::createOne(['organization' => $this->organization]);
            $this->request('POST', $this->rosterUri($squad), ['player_id' => $player->getId(), 'shirt_number' => 10], $this->token);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }
    }

    public function testAPlayerCannotBeInTheSameSquadTwice(): void
    {
        $seasonTeam = $this->registeredClub();
        $player = PlayerFactory::createOne(['organization' => $this->organization]);

        $this->request('POST', $this->rosterUri($seasonTeam), ['player_id' => $player->getId()], $this->token);
        $this->request('POST', $this->rosterUri($seasonTeam), ['player_id' => $player->getId()], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('already_in_squad', $this->json()['code']);
    }

    /**
     * The rule this stage exists to demonstrate: naming a captain demotes the previous one
     * rather than failing. Refusing would make the operator hunt for who currently holds it
     * — a bookkeeping job the computer is better placed to do.
     */
    public function testNamingACaptainDemotesThePreviousOne(): void
    {
        $seasonTeam = $this->registeredClub();
        $uri = $this->rosterUri($seasonTeam);

        $first = PlayerFactory::createOne(['organization' => $this->organization]);
        $second = PlayerFactory::createOne(['organization' => $this->organization]);

        $this->request('POST', $uri, ['player_id' => $first->getId(), 'shirt_number' => 4, 'captain' => true], $this->token);
        $firstEntryId = $this->json()['id'];

        $this->request('POST', $uri, ['player_id' => $second->getId(), 'shirt_number' => 8, 'captain' => true], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->request('GET', $uri, null, $this->token);
        $captains = array_values(array_filter($this->jsonList(), static fn (array $row): bool => $row['captain']));

        self::assertCount(1, $captains, 'A squad has exactly one captain.');
        self::assertNotSame($firstEntryId, $captains[0]['id'], 'And it is the one just named.');
    }

    public function testASquadIsOrderedByShirtNumberWithUnnumberedPlayersLast(): void
    {
        $seasonTeam = $this->registeredClub();
        $uri = $this->rosterUri($seasonTeam);

        foreach ([['Adam', 11], ['Bartek', 2], ['Cezary', null]] as [$name, $number]) {
            $player = PlayerFactory::createOne(['organization' => $this->organization, 'firstName' => $name, 'lastName' => 'Test']);
            $payload = ['player_id' => $player->getId()];

            if (null !== $number) {
                $payload['shirt_number'] = $number;
            }

            $this->request('POST', $uri, $payload, $this->token);
        }

        $this->request('GET', $uri, null, $this->token);

        // NULLs do not sort last on MariaDB by default, so the repository orders on a CASE
        // expression rather than hoping.
        self::assertSame([2, 11, null], array_column($this->jsonList(), 'shirt_number'));
    }

    /**
     * Listing the registered clubs must cost the same number of queries whatever the number
     * of clubs.
     *
     * Asserting a *constant* rather than a threshold is the stronger claim: any N+1 makes the
     * two counts differ, and no arbitrary number has to be maintained as the query changes.
     *
     * There are two N+1s waiting here. `addSelect('t')` stops the club being a lazy proxy;
     * less obviously, `$seasonTeam->getRosterEntries()->count()` on an uninitialised
     * collection loads the entire collection, so counting squads that way is a query per club
     * that fetches rows nobody wanted.
     */
    public function testListingRegisteredClubsCostsTheSameForThreeClubsAsForTen(): void
    {
        $withThree = $this->queriesForListingSeasonWith(3);
        $withTen = $this->queriesForListingSeasonWith(10);

        self::assertSame(
            $withThree,
            $withTen,
            \sprintf('Three clubs took %d queries, ten took %d.', $withThree, $withTen),
        );
    }

    private function queriesForListingSeasonWith(int $clubs): int
    {
        $league = LeagueFactory::createOne(['organization' => $this->organization]);
        $season = SeasonFactory::createOne(['league' => $league]);

        for ($i = 0; $i < $clubs; ++$i) {
            $team = TeamFactory::createOne(['organization' => $this->organization]);
            $seasonTeam = SeasonTeamFactory::createOne(['season' => $season, 'team' => $team]);
            // Squads too, so the count really has something to count.
            RosterEntryFactory::createMany(2, ['seasonTeam' => $seasonTeam]);
        }

        $uri = \sprintf(
            '/api/v1/organizations/%d/leagues/%d/seasons/%d/teams',
            $this->organization->getId(),
            $league->getId(),
            $season->getId(),
        );

        // The profiler is the supported way to count queries since DBAL 4 dropped
        // DebugStack. `collect: false` in the test environment means nothing is gathered
        // until a test asks for it, so this costs the rest of the suite nothing.
        $this->client->enableProfiler();
        $this->request('GET', $uri, null, $this->token);

        self::assertResponseIsSuccessful();
        self::assertCount($clubs, $this->jsonList());

        $profile = $this->client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile, 'the profiler has to be collecting for this to mean anything');

        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    private function registeredClub(): SeasonTeam
    {
        $team = TeamFactory::createOne(['organization' => $this->organization]);

        return SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $team]);
    }

    private function teamsUri(): string
    {
        return \sprintf(
            '/api/v1/organizations/%d/leagues/%d/seasons/%d/teams',
            $this->organization->getId(),
            $this->season->getLeague()->getId(),
            $this->season->getId(),
        );
    }

    private function rosterUri(SeasonTeam $seasonTeam): string
    {
        return $this->teamsUri().'/'.$seasonTeam->getId().'/roster';
    }
}
