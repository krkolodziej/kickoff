<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Organization\OrganizationManager;
use App\Dto\Input\AddMemberRequest;
use App\Dto\Input\UpdateMemberRoleRequest;
use App\Dto\Output\MembershipResource;
use App\Entity\OrganizationMembership;
use App\Repository\OrganizationMembershipRepository;
use App\Scope\OrganizationScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/members')]
final class MembershipController extends AbstractController
{
    public function __construct(
        private readonly OrganizationManager $organizations,
        private readonly OrganizationMembershipRepository $memberships,
    ) {
    }

    #[Route('', name: 'api_members_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(OrganizationScope $scope, Request $request): JsonResponse
    {
        $rows = $this->memberships->findByOrganization(
            $scope->organization(),
            $request->query->getString('search'),
        );

        return $this->json(array_map(MembershipResource::fromEntity(...), $rows));
    }

    #[Route('', name: 'api_members_add', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function add(
        OrganizationScope $scope,
        #[MapRequestPayload] AddMemberRequest $payload,
    ): JsonResponse {
        $membership = $this->organizations->addMember($scope->organization(), $payload->email, $payload->role);

        return $this->json(MembershipResource::fromEntity($membership), Response::HTTP_CREATED);
    }

    #[Route('/{membershipId<\d+>}', name: 'api_members_update', methods: ['PATCH'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function update(
        OrganizationScope $scope,
        int $membershipId,
        #[MapRequestPayload] UpdateMemberRoleRequest $payload,
    ): JsonResponse {
        $membership = $this->membership($scope, $membershipId);

        $this->organizations->changeRole($membership, $payload->role);

        return $this->json(MembershipResource::fromEntity($membership));
    }

    #[Route('/{membershipId<\d+>}', name: 'api_members_remove', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function remove(OrganizationScope $scope, int $membershipId): JsonResponse
    {
        $this->organizations->removeMember($this->membership($scope, $membershipId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The organization is part of the lookup, not a check applied afterwards.
     *
     * Fetching by id alone and then comparing organizations would work until somebody
     * forgot the comparison — at which point an admin of one organization could edit
     * memberships of another simply by guessing an id. Here there is nothing to forget.
     */
    private function membership(OrganizationScope $scope, int $membershipId): OrganizationMembership
    {
        $membership = $this->memberships->findOneInOrganization($scope->organization(), $membershipId);

        if (null === $membership) {
            throw new NotFoundHttpException();
        }

        return $membership;
    }
}
