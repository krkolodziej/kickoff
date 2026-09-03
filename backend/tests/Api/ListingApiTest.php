<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\PlayerFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * The list contract, exercised on one resource because it is the same code everywhere.
 */
final class ListingApiTest extends ApiTestCase
{
    public function testACollectionIsAPlainArrayUntilPagingIsAskedFor(): void
    {
        [$token, $uri] = $this->organizationWithTeams(3);

        $this->request('GET', $uri, null, $token);

        $body = $this->json();
        self::assertArrayNotHasKey('results', $body, 'Small collections should not be wrapped.');
        self::assertCount(3, $this->jsonList());
    }

    public function testAskingForAPageSwitchesToTheEnvelope(): void
    {
        [$token, $uri] = $this->organizationWithTeams(7);

        $this->request('GET', $uri.'?page=2&page_size=3', null, $token);

        $body = $this->json();
        self::assertSame(7, $body['count']);
        self::assertSame(2, $body['page']);
        self::assertSame(3, $body['page_size']);
        self::assertSame(3, $body['next']);
        self::assertSame(1, $body['previous']);
        self::assertCount(3, $body['results']);
    }

    public function testTheLastPageHasNoNext(): void
    {
        [$token, $uri] = $this->organizationWithTeams(7);

        $this->request('GET', $uri.'?page=3&page_size=3', null, $token);

        $body = $this->json();
        self::assertNull($body['next']);
        self::assertCount(1, $body['results'], 'Seven rows in threes leaves one on the last page.');
    }

    public function testPageSizeAloneIsEnoughToPaginate(): void
    {
        [$token, $uri] = $this->organizationWithTeams(4);

        $this->request('GET', $uri.'?page_size=2', null, $token);

        self::assertSame(1, $this->json()['page']);
        self::assertCount(2, $this->json()['results']);
    }

    public function testAnAbsurdPageSizeIsRejectedRatherThanClamped(): void
    {
        [$token, $uri] = $this->organizationWithTeams(1);

        $this->request('GET', $uri.'?page_size=5000', null, $token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('page_size', $this->json()['fields']);
    }

    /**
     * An ordering parameter that quietly does nothing is the kind of bug that survives to
     * production, because the response still looks plausible — just in the wrong order.
     */
    public function testAnUnknownOrderingFieldIsRefusedAndSaysWhatIsAllowed(): void
    {
        [$token, $uri] = $this->organizationWithTeams(1);

        $this->request('GET', $uri.'?order=salary', null, $token);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $body = $this->json();
        self::assertSame('invalid_ordering', $body['code']);
        self::assertStringContainsString('name', $body['detail']);
    }

    public function testOrderingCanBeReversedWithALeadingMinus(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        foreach (['Czarni', 'Alfa', 'Bieszczady'] as $index => $name) {
            TeamFactory::createOne(['organization' => $organization, 'name' => $name, 'slug' => 'club-'.$index]);
        }

        $uri = '/api/v1/organizations/'.$organization->getId().'/teams';
        $token = $this->signIn($owner);

        $this->request('GET', $uri.'?order=name', null, $token);
        self::assertSame(['Alfa', 'Bieszczady', 'Czarni'], array_column($this->jsonList(), 'name'));

        $this->request('GET', $uri.'?order=-name', null, $token);
        self::assertSame(['Czarni', 'Bieszczady', 'Alfa'], array_column($this->jsonList(), 'name'));
    }

    public function testSearchMatchesAFullNameAcrossTwoColumns(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        PlayerFactory::createOne(['organization' => $organization, 'firstName' => 'Jan', 'lastName' => 'Kowalski']);
        PlayerFactory::createOne(['organization' => $organization, 'firstName' => 'Piotr', 'lastName' => 'Nowak']);

        $uri = '/api/v1/organizations/'.$organization->getId().'/players';
        $token = $this->signIn($owner);

        // Neither column contains "Jan Kowalski"; only the two of them concatenated do.
        $this->request('GET', $uri.'?search=Jan+Kowalski', null, $token);
        self::assertCount(1, $this->jsonList());
        self::assertSame('Jan Kowalski', $this->jsonList()[0]['full_name']);

        $this->request('GET', $uri.'?search=nowak', null, $token);
        self::assertCount(1, $this->jsonList());
    }

    /**
     * @return array{string, string}
     */
    private function organizationWithTeams(int $count): array
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        for ($i = 0; $i < $count; ++$i) {
            TeamFactory::createOne([
                'organization' => $organization,
                'name' => \sprintf('Club %02d', $i),
                'slug' => \sprintf('club-%02d', $i),
            ]);
        }

        return [$this->signIn($owner), '/api/v1/organizations/'.$organization->getId().'/teams'];
    }
}
