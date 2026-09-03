<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\OrganizationRole;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMembershipFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Response;

final class MembershipApiTest extends ApiTestCase
{
    public function testAnOwnerCanAddAnExistingAccount(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $newcomer = UserFactory::createOne(['email' => 'newcomer@kickoff.test']);

        $this->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/members',
            ['email' => 'Newcomer@Kickoff.test', 'role' => 'ADMIN'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->json();
        self::assertSame('ADMIN', $body['role']);
        self::assertSame($newcomer->getId(), $body['user_id'], 'The address is matched case-insensitively.');
    }

    public function testAnUnknownEmailIsReportedOnTheField(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $this->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/members',
            ['email' => 'nobody@kickoff.test'],
            $this->signIn($owner),
        );

        // Not a 404: from the caller's point of view it is the value they typed that is
        // wrong, and the form has a field waiting to say so.
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('email', $this->json()['fields']);
    }

    public function testAddingSomebodyTwiceIsAConflict(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $member = UserFactory::createOne(['email' => 'member@kickoff.test']);
        OrganizationMembershipFactory::createOne(['organization' => $organization, 'user' => $member]);

        $this->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/members',
            ['email' => 'member@kickoff.test'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('already_a_member', $this->json()['code']);
    }

    public function testTheApiRefusesToMintASecondOwner(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        UserFactory::createOne(['email' => 'newcomer@kickoff.test']);

        $this->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/members',
            ['email' => 'newcomer@kickoff.test', 'role' => 'OWNER'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('role', $this->json()['fields']);
    }

    /**
     * Both ways of getting the role wrong have to read the same, or the form shows the user
     * a sentence about PHP types instead of one about roles.
     */
    public function testAnUnrecognisedRoleReadsLikeAnUnassignableOne(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        UserFactory::createOne(['email' => 'newcomer@kickoff.test']);
        $token = $this->signIn($owner);
        $uri = '/api/v1/organizations/'.$organization->getId().'/members';

        $this->request('POST', $uri, ['email' => 'newcomer@kickoff.test', 'role' => 'SUPERVISOR'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(['Choose a valid role.'], $this->json()['fields']['role']);

        $this->request('POST', $uri, ['email' => 'newcomer@kickoff.test', 'role' => 'OWNER'], $token);
        self::assertSame(['Choose a valid role.'], $this->json()['fields']['role']);
    }

    public function testTheOwnerMembershipCannotBeDemoted(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $ownerMembership = $organization->getMemberships()->first();
        self::assertNotFalse($ownerMembership);

        $this->request(
            'PATCH',
            '/api/v1/organizations/'.$organization->getId().'/members/'.$ownerMembership->getId(),
            ['role' => 'MEMBER'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('owner_membership_protected', $this->json()['code']);
    }

    public function testTheOwnerMembershipCannotBeRemoved(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $ownerMembership = $organization->getMemberships()->first();
        self::assertNotFalse($ownerMembership);

        $this->request(
            'DELETE',
            '/api/v1/organizations/'.$organization->getId().'/members/'.$ownerMembership->getId(),
            null,
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('owner_membership_protected', $this->json()['code']);
    }

    public function testAMemberMayReadTheRosterButNotChangeIt(): void
    {
        $organization = OrganizationFactory::createOne();
        $member = UserFactory::createOne();
        OrganizationMembershipFactory::createOne([
            'organization' => $organization,
            'user' => $member,
            'role' => OrganizationRole::Member,
        ]);
        UserFactory::createOne(['email' => 'newcomer@kickoff.test']);

        $token = $this->signIn($member);
        $uri = '/api/v1/organizations/'.$organization->getId().'/members';

        // Knowing who else is in the league is not privileged information.
        $this->request('GET', $uri, null, $token);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->jsonList());

        $this->request('POST', $uri, ['email' => 'newcomer@kickoff.test'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * A membership id is unique across the whole table, so the organization has to be part
     * of the lookup rather than compared afterwards. Were it merely compared, forgetting the
     * comparison once would let an admin of one organization edit the roster of another.
     */
    public function testAMembershipFromAnotherOrganizationIsNotReachable(): void
    {
        $owner = UserFactory::createOne();
        $mine = OrganizationFactory::createOne(['createdBy' => $owner]);

        $elsewhere = OrganizationFactory::createOne();
        $strangersMembership = OrganizationMembershipFactory::createOne(['organization' => $elsewhere]);

        $this->request(
            'PATCH',
            '/api/v1/organizations/'.$mine->getId().'/members/'.$strangersMembership->getId(),
            ['role' => 'ADMIN'],
            $this->signIn($owner),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAnOwnerCanChangeARoleAndRemoveAMember(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $membership = OrganizationMembershipFactory::createOne([
            'organization' => $organization,
            'role' => OrganizationRole::Member,
        ]);

        $token = $this->signIn($owner);
        $uri = '/api/v1/organizations/'.$organization->getId().'/members/'.$membership->getId();

        $this->request('PATCH', $uri, ['role' => 'ADMIN'], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('ADMIN', $this->json()['role']);

        $this->request('DELETE', $uri, null, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->request('GET', '/api/v1/organizations/'.$organization->getId().'/members', null, $token);
        self::assertCount(1, $this->jsonList());
    }

    public function testMembersAreListedWithOwnersFirst(): void
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        OrganizationMembershipFactory::createOne(['organization' => $organization, 'role' => OrganizationRole::Member]);
        OrganizationMembershipFactory::createOne(['organization' => $organization, 'role' => OrganizationRole::Admin]);

        $this->request('GET', '/api/v1/organizations/'.$organization->getId().'/members', null, $this->signIn($owner));

        // Alphabetically ADMIN would sort above OWNER, which reads as though the admin ran
        // the place. The repository orders by authority instead.
        self::assertSame(['OWNER', 'ADMIN', 'MEMBER'], array_column($this->jsonList(), 'role'));
    }
}
