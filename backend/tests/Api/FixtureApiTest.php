<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\Season;
use App\Entity\SeasonTeam;
use App\Entity\User;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMembershipFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\SeasonTeamFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Response;

final class FixtureApiTest extends ApiTestCase
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
        $this->season = SeasonFactory::createOne([
            'league' => $league,
            'name' => '2026/27',
            'startDate' => new \DateTimeImmutable('2026-08-15'),
        ]);
        $this->token = $this->signIn($this->owner);
    }

    public function testTwelveClubsBecomeOneHundredAndThirtyTwoFixtures(): void
    {
        $this->register(12);

        $this->request('POST', $this->uri().'/generate', ['double_round' => true], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $fixtures = $this->jsonList();
        self::assertCount(132, $fixtures);
        self::assertSame(22, max(array_column($fixtures, 'round_number')));
    }

    public function testASingleRoundIsHalfOfThat(): void
    {
        $this->register(12);

        $this->request('POST', $this->uri().'/generate', ['double_round' => false], $this->token);

        $fixtures = $this->jsonList();
        self::assertCount(66, $fixtures);
        self::assertSame(11, max(array_column($fixtures, 'round_number')));
        self::assertSame([1], array_values(array_unique(array_column($fixtures, 'leg'))));
    }

    /**
     * The rule the whole stage turns on. Generating twice would double every fixture and
     * leave any results already recorded pointing at half a calendar.
     */
    public function testASecondGenerateIsRefusedAndChangesNothing(): void
    {
        $this->register(6);

        $this->request('POST', $this->uri().'/generate', ['double_round' => false], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $before = \count($this->jsonList());

        $this->request('POST', $this->uri().'/generate', ['double_round' => false], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('fixtures_already_generated', $this->json()['code']);

        $this->request('GET', $this->uri(), null, $this->token);
        self::assertCount($before, $this->jsonList(), 'The calendar is untouched.');
    }

    public function testASeasonWithOneClubCannotBeScheduled(): void
    {
        $this->register(1);

        $this->request('POST', $this->uri().'/generate', ['double_round' => true], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('not_enough_clubs', $this->json()['code']);
    }

    public function testRoundsAreSpacedFromTheGivenDate(): void
    {
        $this->register(4);

        $this->request(
            'POST',
            $this->uri().'/generate',
            ['double_round' => false, 'first_round_on' => '2026-09-05', 'days_between_rounds' => 14],
            $this->token,
        );

        $byRound = [];

        foreach ($this->jsonList() as $fixture) {
            $byRound[$fixture['round_number']] = $fixture['kick_off_at'];
        }

        self::assertStringStartsWith('2026-09-05', $byRound[1]);
        self::assertStringStartsWith('2026-09-19', $byRound[2], 'Fortnightly, as asked.');
        self::assertStringStartsWith('2026-10-03', $byRound[3]);
    }

    public function testTheCalendarCanBeFilteredByRoundAndByClub(): void
    {
        $registered = $this->register(6);

        $this->request('POST', $this->uri().'/generate', ['double_round' => false], $this->token);

        $this->request('GET', $this->uri().'?round=1', null, $this->token);
        self::assertCount(3, $this->jsonList(), 'Six clubs means three fixtures a round.');

        $teamId = $registered[0]->getTeam()->getId();
        $this->request('GET', $this->uri().'?team='.$teamId, null, $this->token);

        $forTheClub = $this->jsonList();
        self::assertCount(5, $forTheClub, 'One club plays each of the other five.');

        // Home *or* away: "show me this club's season" means both.
        foreach ($forTheClub as $fixture) {
            self::assertTrue(
                $fixture['home_team_id'] === $teamId || $fixture['away_team_id'] === $teamId,
            );
        }
    }

    public function testTheCalendarCanBeClearedAndRegenerated(): void
    {
        $this->register(4);

        $this->request('POST', $this->uri().'/generate', ['double_round' => false], $this->token);
        self::assertCount(6, $this->jsonList());

        $this->request('DELETE', $this->uri(), null, $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->request('POST', $this->uri().'/generate', ['double_round' => true], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertCount(12, $this->jsonList());
    }

    public function testAMemberCanReadTheCalendarButNotGenerateIt(): void
    {
        $this->register(4);
        $this->request('POST', $this->uri().'/generate', ['double_round' => false], $this->token);

        $member = UserFactory::createOne();
        OrganizationMembershipFactory::createOne([
            'organization' => $this->organization,
            'user' => $member,
            'role' => OrganizationRole::Member,
        ]);
        $memberToken = $this->signIn($member);

        $this->request('GET', $this->uri(), null, $memberToken);
        self::assertResponseIsSuccessful();
        self::assertCount(6, $this->jsonList());

        $this->request('DELETE', $this->uri(), null, $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return list<SeasonTeam>
     */
    private function register(int $clubs): array
    {
        $registered = [];

        for ($i = 0; $i < $clubs; ++$i) {
            $team = TeamFactory::createOne(['organization' => $this->organization]);
            $registered[] = SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $team]);
        }

        return $registered;
    }

    private function uri(): string
    {
        return \sprintf(
            '/api/v1/organizations/%d/leagues/%d/seasons/%d/fixtures',
            $this->organization->getId(),
            $this->season->getLeague()->getId(),
            $this->season->getId(),
        );
    }
}
