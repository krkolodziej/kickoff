<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Match\MatchLifecycle;
use App\Dto\Output\FixtureResource;
use App\Entity\MatchStatus;
use App\Scope\FixtureScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * One endpoint per transition rather than a single `PATCH {status: "LIVE"}`.
 *
 * A named action says what it means — `/start` is a thing a person does, "set status to LIVE"
 * is a thing a database does — and it leaves no room for a caller to invent a transition the
 * machine does not have. The machine still refuses either way; this just makes the API read
 * like the job.
 */
#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons/{seasonId<\d+>}/fixtures/{fixtureId<\d+>}')]
final class MatchController extends AbstractController
{
    public function __construct(
        private readonly MatchLifecycle $lifecycle,
    ) {
    }

    #[Route('', name: 'api_match_show', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function show(FixtureScope $scope): JsonResponse
    {
        return $this->json(FixtureResource::fromEntity($scope->fixture()));
    }

    #[Route('/start', name: 'api_match_start', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function start(FixtureScope $scope): JsonResponse
    {
        return $this->applied($scope, MatchStatus::Live);
    }

    #[Route('/finish', name: 'api_match_finish', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function finish(FixtureScope $scope): JsonResponse
    {
        return $this->applied($scope, MatchStatus::Finished);
    }

    #[Route('/cancel', name: 'api_match_cancel', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function cancel(FixtureScope $scope): JsonResponse
    {
        return $this->applied($scope, MatchStatus::Cancelled);
    }

    #[Route('/postpone', name: 'api_match_postpone', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function postpone(FixtureScope $scope): JsonResponse
    {
        return $this->applied($scope, MatchStatus::Postponed);
    }

    #[Route('/reschedule', name: 'api_match_reschedule', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function reschedule(FixtureScope $scope): JsonResponse
    {
        return $this->applied($scope, MatchStatus::Scheduled);
    }

    private function applied(FixtureScope $scope, MatchStatus $target): JsonResponse
    {
        $this->lifecycle->transition($scope->fixture(), $target);

        return $this->json(FixtureResource::fromEntity($scope->fixture()));
    }
}
