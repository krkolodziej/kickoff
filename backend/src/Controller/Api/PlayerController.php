<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Player\PlayerProfile;
use App\Dto\Input\ListQuery;
use App\Dto\Input\PlayerRequest;
use App\Dto\Output\PlayerResource;
use App\Entity\Player;
use App\Repository\Listing;
use App\Repository\MatchEventRepository;
use App\Repository\PlayerRepository;
use App\Repository\RosterEntryRepository;
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
        private readonly RosterEntryRepository $rosterEntries,
        private readonly MatchEventRepository $matchEvents,
        private readonly PlayerProfile $profiles,
        private readonly Listing $listing,
    ) {
    }

    #[Route('', name: 'api_players_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(OrganizationScope $scope, #[MapQueryString] ListQuery $query = new ListQuery()): JsonResponse
    {
        $qb = $this->players->scopedQuery($scope->organization(), $query);
        $this->listing->sort($qb, $query, self::ORDERING, 'last_name');

        // respond rather than respond: the club, number and position on each row live on
        // a roster entry and the goals on match events, so a per-row mapper would fetch them
        // one player at a time. Given the page, this is two extra queries whatever its size,
        // which is what the query-count test in PlayerApiTest pins down.
        return $this->json($this->listing->respond($qb, $query, $this->annotate(...)));
    }

    #[Route('', name: 'api_players_create', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function create(OrganizationScope $scope, #[MapRequestPayload] PlayerRequest $payload): JsonResponse
    {
        $player = new Player($scope->organization(), $payload->firstName, $payload->lastName);
        $player->setDateOfBirth($payload->dateOfBirth);

        $this->entityManager->persist($player);
        $this->entityManager->flush();

        // The only caller that legitimately passes the entity alone: a player registered a
        // moment ago is in nobody's squad and has played nothing.
        return $this->json(PlayerResource::fromEntity($player), Response::HTTP_CREATED);
    }

    #[Route('/{playerId<\d+>}', name: 'api_players_show', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function show(OrganizationScope $scope, int $playerId): JsonResponse
    {
        return $this->json($this->annotate([$this->player($scope, $playerId)])[0]);
    }

    /**
     * The person, and what they have done in this organization.
     *
     * One endpoint rather than three, for the reason the standings and statistics endpoints
     * exist: a client that had to fetch the player, then the squads, then a total per season
     * would be making a chain of requests each of which needs the previous one's answer.
     */
    #[Route('/{playerId<\d+>}/profile', name: 'api_players_profile', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function profile(OrganizationScope $scope, int $playerId): JsonResponse
    {
        return $this->json($this->profiles->forPlayer($this->player($scope, $playerId)));
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

        return $this->json($this->annotate([$player])[0]);
    }

    #[Route('/{playerId<\d+>}', name: 'api_players_delete', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function delete(OrganizationScope $scope, int $playerId): JsonResponse
    {
        $this->entityManager->remove($this->player($scope, $playerId));
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Players plus the two things that are true about them but stored somewhere else.
     *
     * Used by the single-resource endpoints as well as by the list. A one-element array costs
     * two queries there, which is the price of `GET /players/5` answering the same thing as
     * row five of `GET /players` — the alternative is an endpoint that quietly reports every
     * player as unattached.
     *
     * @param list<Player> $players
     *
     * @return list<PlayerResource>
     */
    private function annotate(array $players): array
    {
        $ids = array_map(static fn (Player $player): int => (int) $player->getId(), $players);

        $squads = $this->rosterEntries->currentForPlayers($ids);
        $totals = $this->matchEvents->careerTotalsForPlayers($ids);

        return array_map(
            static fn (Player $player): PlayerResource => PlayerResource::fromEntity(
                $player,
                $squads[(int) $player->getId()] ?? null,
                $totals[(int) $player->getId()] ?? null,
            ),
            $players,
        );
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
