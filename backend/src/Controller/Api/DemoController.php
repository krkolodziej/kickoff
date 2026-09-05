<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Command\SeedDemoCommand;
use App\Entity\User;
use App\Repository\OrganizationRepository;
use App\Repository\SeasonRepository;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One click into a league that already has a season in it.
 *
 * Signing somebody in without a credential is exactly the sort of endpoint that should make a
 * reader suspicious, so here is why each guard is where it is.
 *
 * **It only exists when it is turned on.** Off by default, on where the demonstration data is;
 * and when it is off the route answers 404 rather than 403, because a disabled endpoint has no
 * reason to announce that it exists.
 *
 * **It signs in one named account and cannot be pointed at another.** There is no parameter.
 * The email is a constant, so the worst this endpoint can do is what that one account can do.
 *
 * **That account is an administrator, not the owner.** Everything worth showing is open to it;
 * deleting the organization is not, because that needs OWNER. A visitor cannot destroy the
 * thing they came to look at, and no amount of clicking around leaves the demo broken.
 *
 * The response is the same one the login endpoint produces — the same handler builds it — with
 * one key added: where to go. A visitor who lands on a list of organizations has to find the
 * season for themselves, and the season is the entire reason they clicked.
 */
#[Route('/api/v1/auth/demo')]
final class DemoController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandler $authenticationSuccess,
        private readonly UserRepository $users,
        private readonly OrganizationRepository $organizations,
        private readonly SeasonRepository $seasons,
        #[Autowire('%env(bool:DEMO_LOGIN_ENABLED)%')]
        private readonly bool $enabled,
    ) {
    }

    #[Route('', name: 'api_auth_demo', methods: ['POST'])]
    public function signIn(): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException();
        }

        $visitor = $this->users->findOneBy([
            'email' => User::normaliseEmail(SeedDemoCommand::VISITOR_EMAIL),
        ]);

        // Enabled but not seeded. Worth saying plainly rather than answering 404 as though the
        // feature did not exist: this one is a deployment that has not finished setting itself
        // up, and the fix is to run `app:seed:demo` rather than to look for a bug.
        if (null === $visitor) {
            return $this->json([
                'detail' => 'The demonstration data has not been prepared yet.',
                'code' => 'demo_not_ready',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $response = $this->authenticationSuccess->handleAuthenticationSuccess($visitor);

        // The handler's declared return type is Response; at runtime it is a JsonResponse, and
        // `setData()` re-encodes the body while leaving the headers and the refresh cookie
        // that the authentication-success subscribers attached. If that ever stops being true
        // the endpoint quietly goes back to answering exactly what it answered before, which
        // is a worse demo but not a broken sign-in.
        if ($response instanceof JsonResponse) {
            /** @var array<string, mixed> $body */
            $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            $body['demo'] = $this->entryPoint($visitor);

            $response->setData($body);
        }

        return $response;
    }

    /**
     * Where the visitor should be dropped: the most recent season this account can see.
     *
     * Resolved from the visitor's own membership rather than from the seeder's slug, which
     * matters for two reasons. It cannot reach an organization the account is not a member of,
     * and it can be exercised by a test that builds two rows instead of by one that plays
     * seventy matches.
     *
     * Ids, not a path. Every address in this application is built by the client; the one place
     * the server writes a path is a stored notification, which documents itself as the
     * exception because it has to survive a change of host. This is computed per request.
     *
     * Null when there is nothing to enter — an unseeded organization, or a season somebody has
     * since deleted. The client falls back to the organization list rather than to a 404.
     *
     * @return array{organization_id: int, league_id: int, season_id: int}|null
     */
    private function entryPoint(User $visitor): ?array
    {
        $memberships = $this->organizations->findForUser($visitor);
        $organization = $memberships[0]['organization'] ?? null;

        if (null === $organization) {
            return null;
        }

        $season = $this->seasons->latestForOrganization($organization);

        if (null === $season) {
            return null;
        }

        // snake_case by hand. `setData()` calls json_encode directly and does not run the
        // camel-case name converter that every serialized resource in this API relies on, so
        // a `seasonId` written here would reach the client as `seasonId`.
        return [
            'organization_id' => (int) $organization->getId(),
            'league_id' => (int) $season->getLeague()->getId(),
            'season_id' => (int) $season->getId(),
        ];
    }
}
