<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Response;

final class SeasonApiTest extends ApiTestCase
{
    public function testASeasonCanBeCreatedWithoutAnEndDate(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);

        $this->request('POST', $this->uri($league), ['name' => '2026/27', 'start_date' => '2026-08-15'], $this->signIn($owner));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNull($this->json()['end_date'], 'The last round is rarely known in August.');
    }

    /**
     * A date has no time and no timezone. RFC 3339 would invent both, and a client an hour
     * west could then render the day before.
     */
    public function testDatesAreEmittedAsDatesRatherThanInstants(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);

        $this->request(
            'POST',
            $this->uri($league),
            ['name' => '2026/27', 'start_date' => '2026-08-15', 'end_date' => '2027-06-05'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->json();
        self::assertSame('2026-08-15', $body['start_date']);
        self::assertSame('2027-06-05', $body['end_date']);
        // The row's creation, on the other hand, really is an instant.
        self::assertStringContainsString('T', $body['created_at']);
    }

    public function testTheCustomConstraintCatchesYearsThatDoNotFollowOneAnother(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);

        $this->request('POST', $this->uri($league), ['name' => '2026/29', 'start_date' => '2026-08-15'], $this->signIn($owner));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('2026/27', $this->json()['fields']['name'][0]);
    }

    public function testASeasonCannotEndBeforeItStarts(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);

        $this->request(
            'POST',
            $this->uri($league),
            ['name' => '2026', 'start_date' => '2026-08-15', 'end_date' => '2026-06-01'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('end_date', $this->json()['fields']);
    }

    public function testTwoSeasonsOfOneLeagueCannotShareAName(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);
        SeasonFactory::createOne(['league' => $league, 'name' => '2026']);

        $this->request('POST', $this->uri($league), ['name' => '2026', 'start_date' => '2026-08-15'], $this->signIn($owner));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('season_name_taken', $this->json()['code']);
        self::assertArrayHasKey('name', $this->json()['fields']);
    }

    /**
     * Four levels of nesting only mean something if the chain is checked. A season that
     * exists, reached through a league it does not belong to, has to be a missing row.
     */
    public function testASeasonReachedThroughTheWrongLeagueIsNotFound(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $mine = LeagueFactory::createOne(['organization' => $organization]);
        $other = LeagueFactory::createOne(['organization' => $organization]);
        $season = SeasonFactory::createOne(['league' => $other]);

        $this->request('GET', $this->uri($mine).'/'.$season->getId(), null, $this->signIn($owner));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testSeasonsAreListedNewestFirst(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);

        foreach (['2024', '2026', '2025'] as $name) {
            SeasonFactory::createOne([
                'league' => $league,
                'name' => $name,
                'startDate' => new \DateTimeImmutable($name.'-08-01'),
            ]);
        }

        $this->request('GET', $this->uri($league), null, $this->signIn($owner));

        // The season somebody wants is almost always the current one.
        self::assertSame(['2026', '2025', '2024'], array_column($this->jsonList(), 'name'));
    }

    private function uri(object $league): string
    {
        \assert(method_exists($league, 'getOrganization') && method_exists($league, 'getId'));

        return \sprintf(
            '/api/v1/organizations/%d/leagues/%d/seasons',
            $league->getOrganization()->getId(),
            $league->getId(),
        );
    }
}
