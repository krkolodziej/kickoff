<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\PlayerFactory;
use App\Tests\Factory\UserFactory;
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
}
