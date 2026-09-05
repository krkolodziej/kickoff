<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Command\SeedDemoCommand;
use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
 * The response is the same one the login endpoint produces — the same handler builds it — so
 * the client has nothing special to do afterwards.
 */
#[Route('/api/v1/auth/demo')]
final class DemoController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandler $authenticationSuccess,
        private readonly UserRepository $users,
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

        return $this->authenticationSuccess->handleAuthenticationSuccess($visitor);
    }
}
