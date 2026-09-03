<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Squad\SquadManager;
use App\Dto\Input\RegisterTeamRequest;
use App\Dto\Output\SeasonTeamResource;
use App\Repository\SeasonTeamRepository;
use App\Repository\TeamRepository;
use App\Scope\SeasonScope;
use App\Scope\SeasonTeamScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons/{seasonId<\d+>}/teams')]
final class SeasonTeamController extends AbstractController
{
    public function __construct(
        private readonly SquadManager $squads,
        private readonly SeasonTeamRepository $seasonTeams,
        private readonly TeamRepository $teams,
    ) {
    }

    #[Route('', name: 'api_season_teams_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(SeasonScope $scope): JsonResponse
    {
        $rows = $this->seasonTeams->findForSeason($scope->season());

        return $this->json(array_map(
            static fn (array $row): SeasonTeamResource => SeasonTeamResource::fromEntity(
                $row['seasonTeam'],
                $row['squadSize'],
            ),
            $rows,
        ));
    }

    #[Route('', name: 'api_season_teams_register', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function register(SeasonScope $scope, #[MapRequestPayload] RegisterTeamRequest $payload): JsonResponse
    {
        $team = $this->teams->findOneInOrganization($scope->organization(), $payload->teamId);

        if (null === $team) {
            throw new NotFoundHttpException();
        }

        $seasonTeam = $this->squads->registerTeam($scope->season(), $team);

        return $this->json(SeasonTeamResource::fromEntity($seasonTeam, 0), Response::HTTP_CREATED);
    }

    #[Route('/{seasonTeamId<\d+>}', name: 'api_season_teams_withdraw', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function withdraw(SeasonTeamScope $scope): JsonResponse
    {
        $this->squads->withdrawTeam($scope->seasonTeam());

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
