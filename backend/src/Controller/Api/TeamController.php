<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Input\ListQuery;
use App\Dto\Input\TeamRequest;
use App\Dto\Output\TeamResource;
use App\Entity\Team;
use App\Repository\Listing;
use App\Repository\TeamRepository;
use App\Scope\OrganizationScope;
use App\Security\Voter\OrganizationVoter;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/teams')]
final class TeamController extends AbstractController
{
    private const ORDERING = [
        'name' => 't.name',
        'slug' => 't.slug',
        'created_at' => 't.createdAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TeamRepository $teams,
        private readonly Listing $listing,
        private readonly SlugGenerator $slugGenerator,
    ) {
    }

    #[Route('', name: 'api_teams_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(OrganizationScope $scope, #[MapQueryString] ListQuery $query = new ListQuery()): JsonResponse
    {
        $qb = $this->teams->scopedQuery($scope->organization(), $query);
        $this->listing->sort($qb, $query, self::ORDERING, 'name');

        return $this->json($this->listing->respond($qb, $query, TeamResource::fromEntity(...)));
    }

    #[Route('', name: 'api_teams_create', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function create(OrganizationScope $scope, #[MapRequestPayload] TeamRequest $payload): JsonResponse
    {
        $team = new Team($scope->organization(), $payload->name, $this->slug($scope, $payload));
        $team->setShortName($payload->shortName);

        $this->entityManager->persist($team);
        $this->entityManager->flush();

        return $this->json(TeamResource::fromEntity($team), Response::HTTP_CREATED);
    }

    #[Route('/{teamId<\d+>}', name: 'api_teams_show', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function show(OrganizationScope $scope, int $teamId): JsonResponse
    {
        return $this->json(TeamResource::fromEntity($this->team($scope, $teamId)));
    }

    #[Route('/{teamId<\d+>}', name: 'api_teams_update', methods: ['PATCH'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function update(OrganizationScope $scope, int $teamId, #[MapRequestPayload] TeamRequest $payload): JsonResponse
    {
        $team = $this->team($scope, $teamId);
        $team->setName($payload->name);
        $team->setShortName($payload->shortName);

        if (null !== $payload->slug && $payload->slug !== $team->getSlug()) {
            $team->setSlug($this->slug($scope, $payload));
        }

        $this->entityManager->flush();

        return $this->json(TeamResource::fromEntity($team));
    }

    #[Route('/{teamId<\d+>}', name: 'api_teams_delete', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function delete(OrganizationScope $scope, int $teamId): JsonResponse
    {
        $this->entityManager->remove($this->team($scope, $teamId));
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The organization is part of the lookup, never a comparison made afterwards — the same
     * rule as memberships, for the same reason: ids are unique table-wide, so a forgotten
     * comparison would reach into somebody else's organization.
     */
    private function team(OrganizationScope $scope, int $teamId): Team
    {
        $team = $this->teams->findOneInOrganization($scope->organization(), $teamId);

        if (null === $team) {
            throw new NotFoundHttpException();
        }

        return $team;
    }

    private function slug(OrganizationScope $scope, TeamRequest $payload): string
    {
        $organization = $scope->organization();

        return $this->slugGenerator->uniqueSlug(
            null !== $payload->slug && '' !== trim($payload->slug) ? $payload->slug : $payload->name,
            fn (string $candidate): bool => $this->teams->slugExists($organization, $candidate),
        );
    }
}
