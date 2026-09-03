<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Squad\SquadManager;
use App\Dto\Input\RosterEntryRequest;
use App\Dto\Output\RosterEntryResource;
use App\Entity\RosterEntry;
use App\Repository\PlayerRepository;
use App\Repository\RosterEntryRepository;
use App\Scope\SeasonTeamScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons/{seasonId<\d+>}/teams/{seasonTeamId<\d+>}/roster')]
final class RosterController extends AbstractController
{
    public function __construct(
        private readonly SquadManager $squads,
        private readonly RosterEntryRepository $rosterEntries,
        private readonly PlayerRepository $players,
    ) {
    }

    #[Route('', name: 'api_roster_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(SeasonTeamScope $scope): JsonResponse
    {
        $rows = $this->rosterEntries->findForSquad($scope->seasonTeam());

        return $this->json(array_map(RosterEntryResource::fromEntity(...), $rows));
    }

    #[Route('', name: 'api_roster_add', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function add(SeasonTeamScope $scope, #[MapRequestPayload] RosterEntryRequest $payload): JsonResponse
    {
        $player = $this->players->findOneInOrganization($scope->organization(), $payload->playerId);

        if (null === $player) {
            throw new NotFoundHttpException();
        }

        $entry = $this->squads->addToSquad(
            $scope->seasonTeam(),
            $player,
            $payload->shirtNumber,
            $payload->position(),
            $payload->captain,
        );

        return $this->json(RosterEntryResource::fromEntity($entry), Response::HTTP_CREATED);
    }

    #[Route('/{rosterEntryId<\d+>}', name: 'api_roster_update', methods: ['PATCH'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function update(
        SeasonTeamScope $scope,
        int $rosterEntryId,
        #[MapRequestPayload] RosterEntryRequest $payload,
    ): JsonResponse {
        $entry = $this->entry($scope, $rosterEntryId);

        $this->squads->updateSquadEntry(
            $entry,
            $payload->shirtNumber,
            $payload->position(),
            $payload->captain,
        );

        return $this->json(RosterEntryResource::fromEntity($entry));
    }

    #[Route('/{rosterEntryId<\d+>}', name: 'api_roster_remove', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function remove(SeasonTeamScope $scope, int $rosterEntryId): JsonResponse
    {
        $this->squads->removeFromSquad($this->entry($scope, $rosterEntryId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function entry(SeasonTeamScope $scope, int $rosterEntryId): RosterEntry
    {
        $entry = $this->rosterEntries->findOneInSquad($scope->seasonTeam(), $rosterEntryId);

        if (null === $entry) {
            throw new NotFoundHttpException();
        }

        return $entry;
    }
}
