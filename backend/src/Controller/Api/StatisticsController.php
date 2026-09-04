<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\MatchEventRepository;
use App\Scope\SeasonScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons/{seasonId<\d+>}/statistics')]
final class StatisticsController extends AbstractController
{
    /**
     * Straight to the repository, with no service in between. The league table earns a domain
     * class because it composes two repositories and applies rules of its own; this is one
     * query returning finished rows, and a class that only forwards a call would be a layer
     * for the sake of symmetry.
     */
    public function __construct(private readonly MatchEventRepository $events)
    {
    }

    #[Route('', name: 'api_statistics_players', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function players(SeasonScope $scope): JsonResponse
    {
        return $this->json($this->events->seasonPlayerTotals($scope->season()));
    }
}
