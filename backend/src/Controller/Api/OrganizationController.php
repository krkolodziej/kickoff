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

        // Batched, not joined onto the query above: three collection joins beside the
        // membership one would multiply into a cartesian product. See countsFor().
        $counts = $repository->countsFor(array_map(
            static fn (array $row): int => (int) $row['organization']->getId(),
            $rows,
        ));

        return $this->json(array_map(
            static fn (array $row): OrganizationResource => OrganizationResource::fromEntity(
                $row['organization'],
                $row['role'],
                $row['memberCount'],
                $counts[(int) $row['organization']->getId()] ?? null,
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
    public function show(OrganizationScope $scope, OrganizationRepository $repository): JsonResponse
    {
        return $this->json($this->resource($scope, $repository));
    }

    #[Route('/{organizationId<\d+>}', name: 'api_organizations_update', methods: ['PATCH'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function update(
        OrganizationScope $scope,
        #[MapRequestPayload] OrganizationRequest $payload,
        OrganizationRepository $repository,
    ): JsonResponse {
        $this->organizations->rename($scope->organization(), $payload->name, $payload->slug);

        return $this->json($this->resource($scope, $repository));
    }

    /**
     * One organization, with the same counts the list carries.
     *
     * `getMemberships()->count()` on an uninitialised collection loads every membership row
     * in order to count it, so the member count comes from the same grouped query as the
     * rest — which is both cheaper and one fewer place for the two endpoints to disagree.
     */
    private function resource(OrganizationScope $scope, OrganizationRepository $repository): OrganizationResource
    {
        $organization = $scope->organization();
        $id = (int) $organization->getId();

        return OrganizationResource::fromEntity(
            $organization,
            $scope->role(),
            $repository->memberCountFor($id),
            $repository->countsFor([$id])[$id] ?? null,
        );
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
