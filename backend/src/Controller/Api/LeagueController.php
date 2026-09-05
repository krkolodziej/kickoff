<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Input\LeagueRequest;
use App\Dto\Input\ListQuery;
use App\Dto\Output\LeagueResource;
use App\Dto\Output\SeasonRefResource;
use App\Entity\League;
use App\Repository\LeagueRepository;
use App\Repository\Listing;
use App\Repository\SeasonRepository;
use App\Scope\LeagueScope;
use App\Scope\OrganizationScope;
use App\Security\Voter\OrganizationVoter;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues')]
final class LeagueController extends AbstractController
{
    /**
     * Wire name => DQL expression.
     *
     * A caller never names a column: they name a field this resource has agreed to sort on.
     * Renaming a property therefore cannot break the API, and `order` cannot be used to sort
     * by something the resource does not even expose.
     */
    private const ORDERING = [
        'name' => 'l.name',
        'slug' => 'l.slug',
        'created_at' => 'l.createdAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LeagueRepository $leagues,
        private readonly SeasonRepository $seasons,
        private readonly Listing $listing,
        private readonly SlugGenerator $slugGenerator,
    ) {
    }

    #[Route('', name: 'api_leagues_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(OrganizationScope $scope, #[MapQueryString] ListQuery $query = new ListQuery()): JsonResponse
    {
        $qb = $this->leagues->scopedQuery($scope->organization(), $query);
        $this->listing->sort($qb, $query, self::ORDERING, 'name');

        // respond rather than respond: the season count and the latest season are one
        // query for the page and would be one query per row through a per-row mapper.
        return $this->json($this->listing->respond($qb, $query, $this->annotate(...)));
    }

    #[Route('', name: 'api_leagues_create', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function create(OrganizationScope $scope, #[MapRequestPayload] LeagueRequest $payload): JsonResponse
    {
        $league = new League($scope->organization(), $payload->name, $this->slug($scope, $payload));
        $league->setDescription($payload->description);

        $this->entityManager->persist($league);
        $this->entityManager->flush();

        return $this->json(LeagueResource::fromEntity($league), Response::HTTP_CREATED);
    }

    #[Route('/{leagueId<\d+>}', name: 'api_leagues_show', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function show(LeagueScope $scope): JsonResponse
    {
        return $this->json($this->annotate([$scope->league()])[0]);
    }

    #[Route('/{leagueId<\d+>}', name: 'api_leagues_update', methods: ['PATCH'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function update(LeagueScope $scope, #[MapRequestPayload] LeagueRequest $payload): JsonResponse
    {
        $league = $scope->league();
        $league->setName($payload->name);
        $league->setDescription($payload->description);

        if (null !== $payload->slug && $payload->slug !== $league->getSlug()) {
            $league->setSlug($this->slug($scope, $payload));
        }

        $this->entityManager->flush();

        return $this->json($this->annotate([$league])[0]);
    }

    #[Route('/{leagueId<\d+>}', name: 'api_leagues_delete', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function delete(LeagueScope $scope): JsonResponse
    {
        $this->entityManager->remove($scope->league());
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Leagues plus what is inside them.
     *
     * A league on its own is a name; the season count and the current season are what make a
     * row worth opening, and they are one query for the whole page.
     *
     * @param list<League> $leagues
     *
     * @return list<LeagueResource>
     */
    private function annotate(array $leagues): array
    {
        $seasons = $this->seasons->forLeagues(
            array_map(static fn (League $league): int => (int) $league->getId(), $leagues),
        );

        return array_map(
            static function (League $league) use ($seasons): LeagueResource {
                $mine = $seasons[(int) $league->getId()] ?? [];

                return LeagueResource::fromEntity(
                    $league,
                    seasonCount: \count($mine),
                    latestSeason: [] === $mine ? null : SeasonRefResource::fromEntity($mine[0]),
                );
            },
            $leagues,
        );
    }

    private function slug(OrganizationScope|LeagueScope $scope, LeagueRequest $payload): string
    {
        $organization = $scope->organization();

        return $this->slugGenerator->uniqueSlug(
            null !== $payload->slug && '' !== trim($payload->slug) ? $payload->slug : $payload->name,
            fn (string $candidate): bool => $this->leagues->slugExists($organization, $candidate),
        );
    }
}
