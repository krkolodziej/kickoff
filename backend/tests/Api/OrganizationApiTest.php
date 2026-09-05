<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Fixture;
use App\Entity\MatchEvent;
use App\Entity\MatchEventType;
use App\Entity\OrganizationRole;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class OrganizationApiTest extends ApiTestCase
{
    public function testCreatingAnOrganizationMakesTheCreatorItsOwner(): void
    {
        $user = UserFactory::createOne();
        $token = $this->signIn($user);

        $this->request('POST', '/api/v1/organizations', ['name' => 'Podkarpacka Liga Amatorska'], $token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->json();
        self::assertSame('OWNER', $body['my_role']);
        self::assertSame(1, $body['member_count']);
        self::assertSame('podkarpacka-liga-amatorska', $body['slug'], 'The slug is derived from the name.');
    }

    public function testSlugsAreTransliteratedAndMadeUnique(): void
    {
        $user = UserFactory::createOne();
        $token = $this->signIn($user);

        $this->request('POST', '/api/v1/organizations', ['name' => 'Łódzki Związek Piłki'], $token);
        self::assertSame('lodzki-zwiazek-pilki', $this->json()['slug']);

        // A second organization may legitimately share a name. Rejecting the request would
        // mean an error about a field the user never filled in.
        $this->request('POST', '/api/v1/organizations', ['name' => 'Łódzki Związek Piłki'], $token);
        self::assertSame('lodzki-zwiazek-pilki-2', $this->json()['slug']);
    }

    public function testTheListShowsOnlyOrganizationsYouBelongTo(): void
    {
        $user = UserFactory::createOne();
        OrganizationFactory::createOne(['name' => 'Mine', 'createdBy' => $user]);
        OrganizationFactory::createOne(['name' => 'Someone else\'s']);

        $this->request('GET', '/api/v1/organizations', null, $this->signIn($user));

        self::assertResponseIsSuccessful();

        $names = array_column($this->jsonList(), 'name');
        self::assertSame(['Mine'], $names);
    }

    /**
     * The rule this whole stage exists for.
     *
     * A non-member must not be able to tell an organization that exists from one that does
     * not. Answering 403 would confirm the id is real, which is information the caller has
     * not earned — and it does so on every verb, not just the readable one.
     */
    /**
     * @param array<string, mixed>|null $payload
     */
    #[DataProvider('everyVerbOnOneOrganization')]
    public function testANonMemberCannotTellTheOrganizationExists(string $method, ?array $payload): void
    {
        $organization = OrganizationFactory::createOne();
        $outsider = UserFactory::createOne();

        $this->request($method, '/api/v1/organizations/'.$organization->getId(), $payload, $this->signIn($outsider));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('not_found', $this->json()['code']);
        self::assertNotSame('permission_denied', $this->json()['code']);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>|null}>
     */
    public static function everyVerbOnOneOrganization(): iterable
    {
        yield 'read' => ['GET', null];
        yield 'update' => ['PATCH', ['name' => 'Renamed']];
        yield 'delete' => ['DELETE', null];
    }

    public function testAMemberMayReadButNotWrite(): void
    {
        $organization = OrganizationFactory::createOne();
        $member = UserFactory::createOne();
        OrganizationMembershipFactory::createOne([
            'organization' => $organization,
            'user' => $member,
            'role' => OrganizationRole::Member,
        ]);

        $token = $this->signIn($member);

        $this->request('GET', '/api/v1/organizations/'.$organization->getId(), null, $token);
        self::assertResponseIsSuccessful();
        self::assertSame('MEMBER', $this->json()['my_role']);

        // Existence is established, so the honest answer here is 403, not 404.
        $this->request('PATCH', '/api/v1/organizations/'.$organization->getId(), ['name' => 'Renamed'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('permission_denied', $this->json()['code']);
    }

    public function testAnAdminMayRenameButOnlyTheOwnerMayDelete(): void
    {
        $organization = OrganizationFactory::createOne();
        $admin = UserFactory::createOne();
        OrganizationMembershipFactory::createOne([
            'organization' => $organization,
            'user' => $admin,
            'role' => OrganizationRole::Admin,
        ]);

        $token = $this->signIn($admin);
        $uri = '/api/v1/organizations/'.$organization->getId();

        $this->request('PATCH', $uri, ['name' => 'Renamed'], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('Renamed', $this->json()['name']);

        $this->request('DELETE', $uri, null, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testTheOwnerCanDeleteTheOrganization(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $this->request('DELETE', '/api/v1/organizations/'.$organization->getId(), null, $this->signIn($owner));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        OrganizationFactory::assert()->count(0, ['id' => $organization->getId()]);
    }

    /**
     * The test above deletes an empty organization, which is why it never noticed that a
     * populated one could not be deleted at all.
     *
     * A match event points at its scorer with ON DELETE RESTRICT, on purpose. Cascading from
     * the organization reaches players and events by two separate paths, and the database is
     * free to take them in either order — so this used to fail whenever it happened to remove
     * a player while his goals were still there. Worse, it did not fail every time.
     */
    public function testAnOrganizationWithRecordedMatchesCanStillBeDeleted(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $league = LeagueFactory::createOne(['organization' => $organization]);
        $season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);

        $home = TeamFactory::createOne(['organization' => $organization, 'name' => 'Stal']);
        $away = TeamFactory::createOne(['organization' => $organization, 'name' => 'Resovia']);
        $squad = SeasonTeamFactory::createOne(['season' => $season, 'team' => $home]);
        SeasonTeamFactory::createOne(['season' => $season, 'team' => $away]);

        $scorer = PlayerFactory::createOne(['organization' => $organization]);
        RosterEntryFactory::createOne(['seasonTeam' => $squad, 'player' => $scorer, 'shirtNumber' => 9]);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $fixture = new Fixture($season, $home, $away, 1, 1);
        $entityManager->persist($fixture);
        $entityManager->persist(new MatchEvent($fixture, MatchEventType::Goal, 23, $home, $scorer));
        $entityManager->flush();

        $this->request('DELETE', '/api/v1/organizations/'.$organization->getId(), null, $this->signIn($owner));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        OrganizationFactory::assert()->count(0, ['id' => $organization->getId()]);
    }

    public function testAnonymousRequestsAreRejected(): void
    {
        $organization = OrganizationFactory::createOne();

        $this->request('GET', '/api/v1/organizations/'.$organization->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRenamingValidatesTheSlugFormat(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $this->request(
            'PATCH',
            '/api/v1/organizations/'.$organization->getId(),
            ['name' => 'Fine', 'slug' => 'Not A Slug'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('slug', $this->json()['fields']);
    }

    /**
     * An organization card that says only how many people are in it tells a reader nothing
     * about what is inside. The counts are batched rather than joined onto the membership
     * query, which would multiply them against each other.
     */
    public function testAnOrganizationCarriesWhatIsInsideIt(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        LeagueFactory::createMany(2, ['organization' => $organization]);
        TeamFactory::createMany(3, ['organization' => $organization]);
        PlayerFactory::createMany(4, ['organization' => $organization]);

        // Somebody else's, to prove the counts are scoped rather than global.
        LeagueFactory::createMany(5, ['organization' => OrganizationFactory::createOne()]);

        $this->request('GET', '/api/v1/organizations', null, $this->signIn($owner));

        self::assertResponseIsSuccessful();

        $row = $this->jsonList()[0];
        self::assertSame(2, $row['league_count']);
        self::assertSame(3, $row['team_count']);
        self::assertSame(4, $row['player_count']);
        self::assertSame(1, $row['member_count']);

        // And one organization answers the same thing as its row in the list.
        $this->request('GET', '/api/v1/organizations/'.$organization->getId(), null, $this->signIn($owner));

        self::assertSame(
            [$row['league_count'], $row['team_count'], $row['player_count'], $row['member_count']],
            [
                $this->json()['league_count'],
                $this->json()['team_count'],
                $this->json()['player_count'],
                $this->json()['member_count'],
            ],
        );
    }
}
