<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Standings\StandingsCalculator;
use App\Scope\SeasonScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons/{seasonId<\d+>}/standings')]
final class StandingsController extends AbstractController
{
    public function __construct(private readonly StandingsCalculator $standings)
    {
    }

    /**
     * Read-only, and there is no counterpart that writes it. The table is derived from
     * results on every request rather than stored and kept up to date, so there is nothing
     * here to POST to and no way for the table to disagree with the matches behind it.
     */
    #[Route('', name: 'api_standings_show', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function show(SeasonScope $scope): JsonResponse
    {
        return $this->json($this->standings->forSeason($scope->season()));
    }
}
