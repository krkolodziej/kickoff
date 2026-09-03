<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Match\MatchEventRecorder;
use App\Dto\Input\MatchEventRequest;
use App\Dto\Output\MatchEventResource;
use App\Repository\MatchEventRepository;
use App\Repository\PlayerRepository;
use App\Repository\TeamRepository;
use App\Scope\FixtureScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Read and append. There is no PATCH and no DELETE, and that is the design: the score is
 * derived from these rows, so an editable event is a score that can stop matching its own
 * history without anything noticing.
 */
#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons/{seasonId<\d+>}/fixtures/{fixtureId<\d+>}/events')]
final class MatchEventController extends AbstractController
{
    public function __construct(
        private readonly MatchEventRecorder $recorder,
        private readonly MatchEventRepository $events,
        private readonly TeamRepository $teams,
        private readonly PlayerRepository $players,
    ) {
    }

    #[Route('', name: 'api_match_events_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(FixtureScope $scope): JsonResponse
    {
        $rows = $this->events->findForFixture($scope->fixture());

        return $this->json(array_map(MatchEventResource::fromEntity(...), $rows));
    }

    #[Route('', name: 'api_match_events_record', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function record(FixtureScope $scope, #[MapRequestPayload] MatchEventRequest $payload): JsonResponse
    {
        $organization = $scope->organization();

        $team = $this->teams->findOneInOrganization($organization, $payload->teamId);
        $player = $this->players->findOneInOrganization($organization, $payload->playerId);

        if (null === $team || null === $player) {
            throw new NotFoundHttpException();
        }

        $relatedPlayer = null === $payload->relatedPlayerId
            ? null
            : $this->players->findOneInOrganization($organization, $payload->relatedPlayerId);

        if (null !== $payload->relatedPlayerId && null === $relatedPlayer) {
            throw new NotFoundHttpException();
        }

        $event = $this->recorder->record(
            $scope->fixture(),
            $payload->type(),
            $payload->minute,
            $team,
            $player,
            $relatedPlayer,
        );

        return $this->json(MatchEventResource::fromEntity($event), Response::HTTP_CREATED);
    }
}
