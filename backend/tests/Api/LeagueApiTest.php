<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\OrganizationRole;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMembershipFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Response;

final class LeagueApiTest extends ApiTestCase
{
    public function testAManagerCanCreateALeague(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $this->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/leagues',
            ['name' => 'Liga Okręgowa', 'description' => 'Fourth tier.'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('liga-okregowa', $this->json()['slug']);
    }

    /**
     * The slug is unique per organization, not globally. Two associations may each run
     * something called "Liga Okręgowa", and neither has a claim on the words.
     */
    public function testTwoOrganizationsMayEachHaveALeagueOfTheSameName(): void
    {
        $first = UserFactory::createOne();
        $second = UserFactory::createOne();
        $one = OrganizationFactory::createOne(['createdBy' => $first]);
        $other = OrganizationFactory::createOne(['createdBy' => $second]);

        $this->request('POST', '/api/v1/organizations/'.$one->getId().'/leagues', ['name' => 'Liga Okręgowa'], $this->signIn($first));
        self::assertSame('liga-okregowa', $this->json()['slug']);

        $this->request('POST', '/api/v1/organizations/'.$other->getId().'/leagues', ['name' => 'Liga Okręgowa'], $this->signIn($second));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('liga-okregowa', $this->json()['slug'], 'The suffix belongs to a collision inside one organization.');
    }

    public function testASecondLeagueOfTheSameNameIsSuffixedWithinAnOrganization(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $token = $this->signIn($owner);
        $uri = '/api/v1/organizations/'.$organization->getId().'/leagues';

        $this->request('POST', $uri, ['name' => 'Klasa A'], $token);
        $this->request('POST', $uri, ['name' => 'Klasa A'], $token);

        self::assertSame('klasa-a-2', $this->json()['slug']);
    }

    public function testAMemberSeesTheListButCannotAddToIt(): void
    {
        $organization = OrganizationFactory::createOne();
        $member = UserFactory::createOne();
        OrganizationMembershipFactory::createOne([
            'organization' => $organization,
            'user' => $member,
            'role' => OrganizationRole::Member,
        ]);
        LeagueFactory::createOne(['organization' => $organization, 'name' => 'Visible']);

        $token = $this->signIn($member);
        $uri = '/api/v1/organizations/'.$organization->getId().'/leagues';

        $this->request('GET', $uri, null, $token);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->jsonList());

        $this->request('POST', $uri, ['name' => 'Nope'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testLeaguesOfAnotherOrganizationAreInvisible(): void
    {
        $owner = UserFactory::createOne();
        $mine = OrganizationFactory::createOne(['createdBy' => $owner]);
        LeagueFactory::createOne(['organization' => $mine, 'name' => 'Mine']);
        LeagueFactory::createOne(['name' => 'Theirs']);

        $this->request('GET', '/api/v1/organizations/'.$mine->getId().'/leagues', null, $this->signIn($owner));

        self::assertSame(['Mine'], array_column($this->jsonList(), 'name'));
    }

    /**
     * A league reached through the wrong organization has to be a missing row, not a league
     * that happens to belong elsewhere — otherwise the nesting in the URL means nothing.
     */
    public function testALeagueReachedThroughTheWrongOrganizationIsNotFound(): void
    {
        $owner = UserFactory::createOne();
        $mine = OrganizationFactory::createOne(['createdBy' => $owner]);
        $elsewhere = LeagueFactory::createOne();

        $this->request(
            'GET',
            '/api/v1/organizations/'.$mine->getId().'/leagues/'.$elsewhere->getId(),
            null,
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testALeagueCanBeRenamedAndDeleted(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);

        $token = $this->signIn($owner);
        $uri = '/api/v1/organizations/'.$organization->getId().'/leagues/'.$league->getId();

        $this->request('PATCH', $uri, ['name' => 'Klasa Okręgowa', 'description' => 'Renamed.'], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('Klasa Okręgowa', $this->json()['name']);
        self::assertSame('Renamed.', $this->json()['description']);

        $this->request('DELETE', $uri, null, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * What makes a league row worth opening: how many seasons are inside it and which one is
     * current, so the row can link at the season rather than at another list.
     */
    public function testALeagueRowCarriesItsSeasons(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);

        SeasonFactory::createOne([
            'league' => $league,
            'name' => '2025',
            'startDate' => new \DateTimeImmutable('2025-03-01'),
        ]);
        $latest = SeasonFactory::createOne([
            'league' => $league,
            'name' => '2026',
            'startDate' => new \DateTimeImmutable('2026-03-01'),
        ]);

        $this->request(
            'GET',
            '/api/v1/organizations/'.$organization->getId().'/leagues',
            null,
            $this->signIn($owner),
        );

        self::assertResponseIsSuccessful();

        $row = $this->jsonList()[0];
        self::assertSame(2, $row['season_count']);
        self::assertSame('2026', $row['latest_season']['name']);
        self::assertSame($latest->getId(), $row['latest_season']['id']);
    }

    public function testALeagueWithNoSeasonsSaysSo(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        LeagueFactory::createOne(['organization' => $organization]);

        $this->request(
            'GET',
            '/api/v1/organizations/'.$organization->getId().'/leagues',
            null,
            $this->signIn($owner),
        );

        self::assertSame(0, $this->jsonList()[0]['season_count']);
        self::assertNull($this->jsonList()[0]['latest_season']);
    }
}
