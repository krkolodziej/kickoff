<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Input\ListQuery;
use App\Dto\Input\SeasonRequest;
use App\Dto\Output\SeasonResource;
use App\Entity\Season;
use App\Exception\ConflictException;
use App\Repository\Listing;
use App\Repository\SeasonRepository;
use App\Scope\LeagueScope;
use App\Scope\SeasonScope;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons')]
final class SeasonController extends AbstractController
{
    private const ORDERING = [
        'name' => 's.name',
        'start_date' => 's.startDate',
        'end_date' => 's.endDate',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SeasonRepository $seasons,
        private readonly Listing $listing,
    ) {
    }

    #[Route('', name: 'api_seasons_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(LeagueScope $scope, #[MapQueryString] ListQuery $query = new ListQuery()): JsonResponse
    {
        $qb = $this->seasons->scopedQuery($scope->league(), $query);
        // Newest first: the season somebody wants is almost always the current one.
        $this->listing->sort($qb, $query, self::ORDERING, '-start_date');

        // Nothing to annotate: a season answers for itself, so the page maps straight through.
        // Lists whose rows need a joined fact use this hook to fetch it once for the page.
        return $this->json($this->listing->respond($qb, $query, $this->resources(...)));
    }

    #[Route('', name: 'api_seasons_create', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function create(LeagueScope $scope, #[MapRequestPayload] SeasonRequest $payload): JsonResponse
    {
        $this->guardName($scope, $payload->name);

        \assert(null !== $payload->startDate);
        $season = new Season($scope->league(), $payload->name, $payload->startDate);
        $season->setEndDate($payload->endDate);

        $this->entityManager->persist($season);
        $this->entityManager->flush();

        return $this->json(SeasonResource::fromEntity($season), Response::HTTP_CREATED);
    }

    #[Route('/{seasonId<\d+>}', name: 'api_seasons_show', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function show(SeasonScope $scope): JsonResponse
    {
        return $this->json(SeasonResource::fromEntity($scope->season()));
    }

    #[Route('/{seasonId<\d+>}', name: 'api_seasons_update', methods: ['PATCH'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function update(SeasonScope $scope, #[MapRequestPayload] SeasonRequest $payload): JsonResponse
    {
        $season = $scope->season();
        $this->guardName($scope, $payload->name, (int) $season->getId());

        \assert(null !== $payload->startDate);
        $season->setName($payload->name);
        $season->setStartDate($payload->startDate);
        $season->setEndDate($payload->endDate);

        $this->entityManager->flush();

        return $this->json(SeasonResource::fromEntity($season));
    }

    #[Route('/{seasonId<\d+>}', name: 'api_seasons_delete', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function delete(SeasonScope $scope): JsonResponse
    {
        $this->entityManager->remove($scope->season());
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Checked here rather than left to the unique index, so the operator gets a message on
     * the field instead of a 500. The index remains what actually guarantees it.
     */
    private function guardName(LeagueScope|SeasonScope $scope, string $name, ?int $exceptId = null): void
    {
        if ($this->seasons->nameExists($scope->league(), $name, $exceptId)) {
            throw new ConflictException(
                \sprintf('This league already has a season called "%s".', $name),
                'season_name_taken',
                ['name' => ['This league already has a season with that name.']],
            );
        }
    }

    /**
     * @param list<Season> $seasons
     *
     * @return list<SeasonResource>
     */
    private function resources(array $seasons): array
    {
        return array_map(SeasonResource::fromEntity(...), $seasons);
    }
}
