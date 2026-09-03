<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Organization\OrganizationManager;
use App\Dto\Input\OrganizationRequest;
use App\Dto\Output\OrganizationResource;
use App\Entity\User;
use App\Repository\OrganizationRepository;
use App\Scope\OrganizationScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Note what is absent from every method below: no query building, no membership lookup, no
 * `if ($role === ...)`. The scope argument carries proof of membership and `#[IsGranted]`
 * carries the role decision, so the bodies are left with the work itself.
 */
#[Route('/api/v1/organizations')]
final class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly OrganizationManager $organizations,
    ) {
    }

    #[Route('', name: 'api_organizations_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        Request $request,
        OrganizationRepository $repository,
    ): JsonResponse {
        $rows = $repository->findForUser($user, $request->query->getString('search'));

        return $this->json(array_map(
            static fn (array $row): OrganizationResource => OrganizationResource::fromEntity(
                $row['organization'],
                $row['role'],
                $row['memberCount'],
            ),
            $rows,
        ));
    }

    #[Route('', name: 'api_organizations_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] OrganizationRequest $payload,
    ): JsonResponse {
        $membership = $this->organizations->create($user, $payload->name, $payload->slug);

        return $this->json(
            OrganizationResource::fromEntity($membership->getOrganization(), $membership->getRole(), 1),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{organizationId<\d+>}', name: 'api_organizations_show', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function show(OrganizationScope $scope): JsonResponse
    {
        return $this->json(OrganizationResource::fromEntity(
            $scope->organization(),
            $scope->role(),
            $scope->organization()->getMemberships()->count(),
        ));
    }

    #[Route('/{organizationId<\d+>}', name: 'api_organizations_update', methods: ['PATCH'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function update(
        OrganizationScope $scope,
        #[MapRequestPayload] OrganizationRequest $payload,
    ): JsonResponse {
        $this->organizations->rename($scope->organization(), $payload->name, $payload->slug);

        return $this->json(OrganizationResource::fromEntity(
            $scope->organization(),
            $scope->role(),
            $scope->organization()->getMemberships()->count(),
        ));
    }

    /**
     * Owner only. An admin runs the competition; ending the organization is a different
     * kind of decision, and it takes every league, club and match with it.
     */
    #[Route('/{organizationId<\d+>}', name: 'api_organizations_delete', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::OWN, subject: 'scope')]
    public function delete(OrganizationScope $scope): JsonResponse
    {
        $this->organizations->delete($scope->organization());

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
