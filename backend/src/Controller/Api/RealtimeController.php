<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Realtime\MatchTopic;
use App\Scope\FixtureScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Hands out permission to watch one match, and nothing else.
 *
 * The hub knows nothing about organizations, memberships or roles — it only checks that a
 * subscriber's token names the topic being asked for. So the decision is made here, where the
 * application already knows how to make it: `FixtureScope` resolves the match through the
 * caller's own membership, which means a stranger gets a 404 before any token is minted.
 *
 * The token then names **exactly one topic**. Not a wildcard, not the season: a token wide
 * enough to be convenient is a token that outlives the reason it was issued.
 *
 * It travels in an httpOnly cookie rather than in the response body, for a practical reason
 * and a security one. `EventSource` cannot set request headers, so a token in the body would
 * have to be pasted into the query string, where it lands in logs and history. And the same
 * argument that keeps the refresh token out of JavaScript applies here.
 */
#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons/{seasonId<\d+>}/fixtures/{fixtureId<\d+>}/realtime')]
final class RealtimeController extends AbstractController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly HubInterface $hub,
    ) {
    }

    #[Route('', name: 'api_realtime_subscribe', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function subscribe(FixtureScope $scope, Request $request): JsonResponse
    {
        $topic = MatchTopic::for($scope->fixture());

        $response = $this->json([
            'hub' => $this->hub->getPublicUrl(),
            'topic' => $topic,
        ]);

        $response->headers->setCookie(
            $this->authorization->createCookie($request, [$topic]),
        );

        return $response;
    }
}
