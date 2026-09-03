<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Fixture;
use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\Player;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMembershipFactory;
use App\Tests\Factory\PlayerFactory;
use App\Tests\Factory\RosterEntryFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\SeasonTeamFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class MatchApiTest extends ApiTestCase
{
    private User $owner;
    private Organization $organization;
    private Season $season;
    private Team $home;
    private Team $away;
    private Player $homePlayer;
    private Player $awayPlayer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = UserFactory::createOne();
        $this->organization = OrganizationFactory::createOne(['createdBy' => $this->owner]);
        $league = LeagueFactory::createOne(['organization' => $this->organization]);
        $this->season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);

        $this->home = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Stal']);
        $this->away = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Resovia']);

        $homeSquad = SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $this->home]);
        $awaySquad = SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $this->away]);

        $this->homePlayer = PlayerFactory::createOne(['organization' => $this->organization, 'firstName' => 'Jan', 'lastName' => 'Kowalski']);
        $this->awayPlayer = PlayerFactory::createOne(['organization' => $this->organization, 'firstName' => 'Piotr', 'lastName' => 'Nowak']);

        RosterEntryFactory::createOne(['seasonTeam' => $homeSquad, 'player' => $this->homePlayer, 'shirtNumber' => 9]);
        RosterEntryFactory::createOne(['seasonTeam' => $awaySquad, 'player' => $this->awayPlayer, 'shirtNumber' => 9]);

        $this->token = $this->signIn($this->owner);
    }

    public function testAMatchStartsAndRecordsTheKickOffTime(): void
    {
        $fixture = $this->fixture();

        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);

        self::assertResponseIsSuccessful();

        $body = $this->json();
        self::assertSame('LIVE', $body['status']);
        self::assertNotNull($body['started_at']);
        self::assertSame(['FINISHED', 'CANCELLED', 'POSTPONED'], $body['allowed_transitions']);
    }

    /**
     * The heart of the stage: the score is never typed in. It moves because a goal was
     * recorded, in the same transaction as the goal.
     */
    public function testAGoalMovesTheScoreAndLeavesATrail(): void
    {
        $fixture = $this->fixture();
        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);

        $this->request('POST', $this->uri($fixture).'/events', [
            'type' => 'GOAL',
            'minute' => 23,
            'team_id' => $this->home->getId(),
            'player_id' => $this->homePlayer->getId(),
        ], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertTrue($this->json()['home'], 'The event knows which side of the timeline it is on.');

        $this->request('GET', $this->uri($fixture), null, $this->token);
        self::assertSame(1, $this->json()['home_score']);
        self::assertSame(0, $this->json()['away_score']);

        $this->request('GET', $this->uri($fixture).'/events', null, $this->token);
        $events = $this->jsonList();
        self::assertCount(1, $events);
        self::assertSame('Jan Kowalski', $events[0]['player_name']);
        self::assertSame(23, $events[0]['minute']);
    }

    public function testAGoalForTheAwaySideMovesTheOtherNumber(): void
    {
        $fixture = $this->fixture();
        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);

        $this->recordGoal($fixture, $this->away, $this->awayPlayer);

        $this->request('GET', $this->uri($fixture), null, $this->token);
        self::assertSame(0, $this->json()['home_score']);
        self::assertSame(1, $this->json()['away_score']);
    }

    public function testEventsAreRefusedBeforeKickOff(): void
    {
        $fixture = $this->fixture();

        $this->request('POST', $this->uri($fixture).'/events', [
            'type' => 'GOAL',
            'minute' => 5,
            'team_id' => $this->home->getId(),
            'player_id' => $this->homePlayer->getId(),
        ], $this->token);

        // 409, not 422: the payload is fine, the match is not in a state that accepts it.
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('match_not_live', $this->json()['code']);
    }

    public function testAPlayerFromTheOtherSquadCannotScore(): void
    {
        $fixture = $this->fixture();
        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);

        $this->request('POST', $this->uri($fixture).'/events', [
            'type' => 'GOAL',
            'minute' => 12,
            'team_id' => $this->home->getId(),
            'player_id' => $this->awayPlayer->getId(),
        ], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('player_id', $this->json()['fields']);
    }

    public function testAClubThatIsNotPlayingCannotAppearOnTheSheet(): void
    {
        $stranger = TeamFactory::createOne(['organization' => $this->organization]);
        $fixture = $this->fixture();
        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);

        $this->request('POST', $this->uri($fixture).'/events', [
            'type' => 'YELLOW_CARD',
            'minute' => 30,
            'team_id' => $stranger->getId(),
            'player_id' => $this->homePlayer->getId(),
        ], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('team_id', $this->json()['fields']);
    }

    public function testASubstitutionNeedsASecondRosteredPlayer(): void
    {
        $fixture = $this->fixture();
        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);
        $uri = $this->uri($fixture).'/events';

        // Without the player coming on.
        $this->request('POST', $uri, [
            'type' => 'SUBSTITUTION',
            'minute' => 60,
            'team_id' => $this->home->getId(),
            'player_id' => $this->homePlayer->getId(),
        ], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('related_player_id', $this->json()['fields']);

        // Coming on for himself.
        $this->request('POST', $uri, [
            'type' => 'SUBSTITUTION',
            'minute' => 60,
            'team_id' => $this->home->getId(),
            'player_id' => $this->homePlayer->getId(),
            'related_player_id' => $this->homePlayer->getId(),
        ], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Coming on from the other club's squad.
        $this->request('POST', $uri, [
            'type' => 'SUBSTITUTION',
            'minute' => 60,
            'team_id' => $this->home->getId(),
            'player_id' => $this->homePlayer->getId(),
            'related_player_id' => $this->awayPlayer->getId(),
        ], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testASubstitutionWorksWithTwoPlayersFromTheSameSquad(): void
    {
        $bench = PlayerFactory::createOne(['organization' => $this->organization, 'firstName' => 'Adam', 'lastName' => 'Nowy']);
        $squad = SeasonTeamFactory::find(['season' => $this->season, 'team' => $this->home]);
        RosterEntryFactory::createOne(['seasonTeam' => $squad, 'player' => $bench, 'shirtNumber' => 17]);

        $fixture = $this->fixture();
        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);

        $this->request('POST', $this->uri($fixture).'/events', [
            'type' => 'SUBSTITUTION',
            'minute' => 62,
            'team_id' => $this->home->getId(),
            'player_id' => $this->homePlayer->getId(),
            'related_player_id' => $bench->getId(),
        ], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('Adam Nowy', $this->json()['related_player_name']);
    }

    public function testACardCannotCarryASecondPlayer(): void
    {
        $fixture = $this->fixture();
        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);

        $this->request('POST', $this->uri($fixture).'/events', [
            'type' => 'RED_CARD',
            'minute' => 70,
            'team_id' => $this->home->getId(),
            'player_id' => $this->homePlayer->getId(),
            'related_player_id' => $this->awayPlayer->getId(),
        ], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('related_player_id', $this->json()['fields']);
    }

    public function testAnIllegalTransitionIsRefusedAndSaysWhatIsAllowed(): void
    {
        $fixture = $this->fixture();

        // A scheduled match cannot be finished — it has not started.
        $this->request('POST', $this->uri($fixture).'/finish', null, $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $body = $this->json();
        self::assertSame('invalid_transition', $body['code']);
        self::assertStringContainsString('live', $body['detail']);
    }

    public function testAFinishedMatchIsNotReopened(): void
    {
        $fixture = $this->fixture();
        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);
        $this->request('POST', $this->uri($fixture).'/finish', null, $this->token);

        self::assertResponseIsSuccessful();
        self::assertNotNull($this->json()['finished_at']);
        self::assertSame([], $this->json()['allowed_transitions']);

        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    /**
     * A match postponed at half time and resumed keeps the kick-off it actually had — every
     * minute already recorded is measured from it.
     */
    public function testResumingAPostponedMatchKeepsTheOriginalKickOff(): void
    {
        $fixture = $this->fixture();

        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);
        $startedAt = $this->json()['started_at'];

        $this->request('POST', $this->uri($fixture).'/postpone', null, $this->token);
        self::assertResponseIsSuccessful();

        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);
        self::assertSame($startedAt, $this->json()['started_at']);
    }

    /**
     * Sending it back to the calendar is different: it has not started, so the clock is
     * cleared. Leaving a stale kick-off would make a rescheduled match look played.
     */
    public function testReschedulingClearsTheKickOff(): void
    {
        $fixture = $this->fixture();

        $this->request('POST', $this->uri($fixture).'/start', null, $this->token);
        $this->request('POST', $this->uri($fixture).'/postpone', null, $this->token);
        $this->request('POST', $this->uri($fixture).'/reschedule', null, $this->token);

        self::assertResponseIsSuccessful();
        self::assertSame('SCHEDULED', $this->json()['status']);
        self::assertNull($this->json()['started_at']);
    }

    public function testTheCalendarCanBeFilteredByStatus(): void
    {
        // Two more clubs, so the calendar has six fixtures rather than the single one that
        // two clubs produce — otherwise "SCHEDULED and LIVE" cannot tell itself from "LIVE".
        foreach (range(1, 2) as $ignored) {
            $team = TeamFactory::createOne(['organization' => $this->organization]);
            SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $team]);
        }

        $this->generateCalendar();

        $this->request('GET', $this->fixturesUri(), null, $this->token);
        $all = $this->jsonList();
        self::assertCount(6, $all, 'Four clubs, single round.');

        $this->request('POST', $this->fixturesUri().'/'.$all[0]['id'].'/start', null, $this->token);
        $this->request('POST', $this->fixturesUri().'/'.$all[1]['id'].'/cancel', null, $this->token);

        $this->request('GET', $this->fixturesUri().'?status=LIVE', null, $this->token);
        self::assertCount(1, $this->jsonList());

        $this->request('GET', $this->fixturesUri().'?status=SCHEDULED,LIVE', null, $this->token);
        self::assertCount(5, $this->jsonList(), 'Four still scheduled, one live, one cancelled.');

        // Case-insensitive, because a query string typed by hand rarely shouts.
        $this->request('GET', $this->fixturesUri().'?status=cancelled', null, $this->token);
        self::assertCount(1, $this->jsonList());
    }

    public function testAnUnknownStatusFilterIsRefused(): void
    {
        $this->generateCalendar();

        $this->request('GET', $this->fixturesUri().'?status=HALFTIME', null, $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('HALFTIME', $this->json()['detail']);
    }

    public function testAMemberCanWatchButNotRun(): void
    {
        $fixture = $this->fixture();
        $member = UserFactory::createOne();
        OrganizationMembershipFactory::createOne([
            'organization' => $this->organization,
            'user' => $member,
            'role' => OrganizationRole::Member,
        ]);
        $memberToken = $this->signIn($member);

        $this->request('GET', $this->uri($fixture), null, $memberToken);
        self::assertResponseIsSuccessful();

        $this->request('POST', $this->uri($fixture).'/start', null, $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function recordGoal(Fixture $fixture, Team $team, Player $player): void
    {
        $this->request('POST', $this->uri($fixture).'/events', [
            'type' => 'GOAL',
            'minute' => 33,
            'team_id' => $team->getId(),
            'player_id' => $player->getId(),
        ], $this->token);
    }

    private function generateCalendar(): void
    {
        $this->request('POST', $this->fixturesUri().'/generate', ['double_round' => false], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    private function fixture(): Fixture
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $fixture = new Fixture($this->season, $this->home, $this->away, 1, 1);
        $entityManager->persist($fixture);
        $entityManager->flush();

        return $fixture;
    }

    private function fixturesUri(): string
    {
        return \sprintf(
            '/api/v1/organizations/%d/leagues/%d/seasons/%d/fixtures',
            $this->organization->getId(),
            $this->season->getLeague()->getId(),
            $this->season->getId(),
        );
    }

    private function uri(Fixture $fixture): string
    {
        return $this->fixturesUri().'/'.$fixture->getId();
    }
}
