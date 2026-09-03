<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Input\ListQuery;
use App\Dto\Input\PlayerRequest;
use App\Dto\Output\PlayerResource;
use App\Entity\Player;
use App\Repository\Listing;
use App\Repository\PlayerRepository;
use App\Scope\OrganizationScope;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/players')]
final class PlayerController extends AbstractController
{
    private const ORDERING = [
        'last_name' => 'p.lastName',
        'first_name' => 'p.firstName',
        'date_of_birth' => 'p.dateOfBirth',
        'created_at' => 'p.createdAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlayerRepository $players,
        private readonly Listing $listing,
    ) {
    }

    #[Route('', name: 'api_players_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(OrganizationScope $scope, #[MapQueryString] ListQuery $query = new ListQuery()): JsonResponse
    {
        $qb = $this->players->scopedQuery($scope->organization(), $query);
        $this->listing->sort($qb, $query, self::ORDERING, 'last_name');

        return $this->json($this->listing->respond($qb, $query, PlayerResource::fromEntity(...)));
    }

    #[Route('', name: 'api_players_create', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function create(OrganizationScope $scope, #[MapRequestPayload] PlayerRequest $payload): JsonResponse
    {
        $player = new Player($scope->organization(), $payload->firstName, $payload->lastName);
        $player->setDateOfBirth($payload->dateOfBirth);

        $this->entityManager->persist($player);
        $this->entityManager->flush();

        return $this->json(PlayerResource::fromEntity($player), Response::HTTP_CREATED);
    }

    #[Route('/{playerId<\d+>}', name: 'api_players_show', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function show(OrganizationScope $scope, int $playerId): JsonResponse
    {
        return $this->json(PlayerResource::fromEntity($this->player($scope, $playerId)));
    }

    #[Route('/{playerId<\d+>}', name: 'api_players_update', methods: ['PATCH'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function update(OrganizationScope $scope, int $playerId, #[MapRequestPayload] PlayerRequest $payload): JsonResponse
    {
        $player = $this->player($scope, $playerId);
        $player->setFirstName($payload->firstName);
        $player->setLastName($payload->lastName);
        $player->setDateOfBirth($payload->dateOfBirth);

        $this->entityManager->flush();

        return $this->json(PlayerResource::fromEntity($player));
    }

    #[Route('/{playerId<\d+>}', name: 'api_players_delete', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function delete(OrganizationScope $scope, int $playerId): JsonResponse
    {
        $this->entityManager->remove($this->player($scope, $playerId));
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function player(OrganizationScope $scope, int $playerId): Player
    {
        $player = $this->players->findOneInOrganization($scope->organization(), $playerId);

        if (null === $player) {
            throw new NotFoundHttpException();
        }

        return $player;
    }
}
