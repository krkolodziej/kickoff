<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Fixture;
use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Message\MatchFinished;
use App\MessageHandler\MatchFinishedHandler;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMembershipFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\SeasonTeamFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class NotificationApiTest extends ApiTestCase
{
    private User $owner;
    private User $admin;
    private User $member;
    private Organization $organization;
    private Season $season;
    private Team $home;
    private Team $away;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = UserFactory::createOne(['email' => 'owner@kickoff.test']);
        $this->admin = UserFactory::createOne(['email' => 'admin@kickoff.test']);
        $this->member = UserFactory::createOne(['email' => 'member@kickoff.test']);

        // The factory makes the creator an owner on its own — an organization with nobody in
        // charge is a row it refuses to produce — so only the other two are added here.
        $this->organization = OrganizationFactory::createOne(['createdBy' => $this->owner]);
        OrganizationMembershipFactory::createOne([
            'organization' => $this->organization,
            'user' => $this->admin,
            'role' => OrganizationRole::Admin,
        ]);
        OrganizationMembershipFactory::createOne([
            'organization' => $this->organization,
            'user' => $this->member,
            'role' => OrganizationRole::Member,
        ]);

        $league = LeagueFactory::createOne(['organization' => $this->organization]);
        $this->season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);

        $this->home = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Stal']);
        $this->away = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Resovia']);
        SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $this->home]);
        SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $this->away]);
    }

    /**
     * Owners and administrators are told; members are not.
     *
     * A notification that goes to everybody is one everybody learns to dismiss, and nothing
     * here needs a spectator's attention.
     */
    public function testAFinishedMatchReachesTheOrganizersAndNobodyElse(): void
    {
        $this->handle($this->finishedFixture());

        self::assertSame(1, $this->unreadCountFor($this->owner));
        self::assertSame(1, $this->unreadCountFor($this->admin));
        self::assertSame(0, $this->unreadCountFor($this->member));
    }

    /**
     * The point of the whole dedupe key.
     *
     * A queue promises *at least once*: a worker that dies between handling a message and
     * acknowledging it is handed the same message again. Running the handler twice is exactly
     * that, and it must not double anybody's bell.
     */
    public function testHandlingTheSameMessageTwiceChangesNothing(): void
    {
        $fixture = $this->finishedFixture();

        $this->handle($fixture);
        $this->handle($fixture);

        self::assertSame(1, $this->unreadCountFor($this->owner));
    }

    /**
     * A match reopened and cancelled between the dispatch and the handling should announce
     * nothing. The message says what happened; the row says whether it still holds.
     */
    public function testAMessageAboutAMatchThatIsNoLongerFinishedIsDropped(): void
    {
        $fixture = $this->finishedFixture();
        $id = (int) $fixture->getId();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $reloaded = $entityManager->find(Fixture::class, $id);
        self::assertNotNull($reloaded);
        $reloaded->setStatus(\App\Entity\MatchStatus::Live);
        $entityManager->flush();

        $this->handleId($id);

        self::assertSame(0, $this->unreadCountFor($this->owner));
    }

    public function testTheBellListsWhatHappenedAndLinksToIt(): void
    {
        $this->handle($this->finishedFixture());

        $token = $this->signIn($this->owner);
        $this->request('GET', '/api/v1/notifications', null, $token);

        self::assertResponseIsSuccessful();
        $rows = $this->jsonList();

        self::assertCount(1, $rows);
        self::assertSame('MATCH_FINISHED', $rows[0]['type']);
        self::assertStringContainsString('Stal', (string) $rows[0]['title']);
        self::assertStringStartsWith('/organizations/', (string) $rows[0]['link']);
        self::assertNull($rows[0]['read_at']);
        self::assertSame($this->organization->getName(), $rows[0]['organization_name']);
    }

    public function testMarkingEverythingReadEmptiesTheCounter(): void
    {
        $this->handle($this->finishedFixture());
        $token = $this->signIn($this->owner);

        $this->request('POST', '/api/v1/notifications/read', null, $token);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['marked']);

        $this->request('GET', '/api/v1/notifications/unread-count', null, $token);
        self::assertSame(0, $this->json()['count']);

        // Doing it again marks nothing: the timestamp answers "when did they first see this",
        // and rewriting it on every call would make the column useless.
        $this->request('POST', '/api/v1/notifications/read', null, $token);
        self::assertSame(0, $this->json()['marked']);
    }

    public function testANotificationBelongingToSomebodyElseIsNotFound(): void
    {
        $this->handle($this->finishedFixture());

        $ownersToken = $this->signIn($this->owner);
        $this->request('GET', '/api/v1/notifications', null, $ownersToken);
        $id = $this->jsonList()[0]['id'];

        $strangersToken = $this->signIn($this->member);
        $this->request('POST', \sprintf('/api/v1/notifications/%d/read', $id), null, $strangersToken);

        // 404, not 403 — the same rule as everywhere else: refusing would confirm it exists.
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testTheBellNeedsAnAccount(): void
    {
        $this->request('GET', '/api/v1/notifications');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function unreadCountFor(User $user): int
    {
        $this->request('GET', '/api/v1/notifications/unread-count', null, $this->signIn($user));
        self::assertResponseIsSuccessful();

        return $this->json()['count'];
    }

    private function handle(Fixture $fixture): void
    {
        $this->handleId((int) $fixture->getId());
    }

    /**
     * The handler is invoked directly rather than through a worker. That the *dispatch* lands
     * in the transport, and rolls back with its transaction, is proven separately in
     * MessengerTransactionTest; here the question is what the handler does once it arrives.
     */
    private function handleId(int $fixtureId): void
    {
        $handler = self::getContainer()->get(MatchFinishedHandler::class);
        $handler(new MatchFinished($fixtureId));
    }

    private function finishedFixture(): Fixture
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $season = $entityManager->find(Season::class, $this->season->getId());
        $home = $entityManager->find(Team::class, $this->home->getId());
        $away = $entityManager->find(Team::class, $this->away->getId());
        self::assertNotNull($season);
        self::assertNotNull($home);
        self::assertNotNull($away);

        $fixture = new Fixture($season, $home, $away, 1, 1);
        $fixture->setStatus(\App\Entity\MatchStatus::Finished);
        $entityManager->persist($fixture);
        $entityManager->flush();

        return $fixture;
    }
}
