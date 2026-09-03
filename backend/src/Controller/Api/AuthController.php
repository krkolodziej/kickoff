<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Input\RegisterRequest;
use App\Dto\Output\UserResource;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private readonly AuthenticationSuccessHandler $authenticationSuccess,
    ) {
    }

    /**
     * Never actually runs.
     *
     * The route has to exist, because routing happens before the firewall and an unmatched
     * path would 404 long before `json_login` got a chance to look at the body. The
     * authenticator intercepts the request, so this body is unreachable — and saying so
     * loudly beats an empty method someone later "fixes".
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Handled by the json_login authenticator on the "login" firewall.');
    }

    /**
     * Creates the account and signs it in immediately: same body as login, plus the refresh
     * cookie, because Gesdinet attaches it on the AuthenticationSuccessEvent that the
     * handler dispatches.
     */
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterRequest $payload,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = new User($payload->email);
        $user->setFirstName($payload->firstName);
        $user->setLastName($payload->lastName);
        $user->setPassword($passwordHasher->hashPassword($user, $payload->password));

        $entityManager->persist($user);
        $entityManager->flush();

        $response = $this->authenticationSuccess->handleAuthenticationSuccess($user);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(UserResource::fromEntity($user));
    }

    /**
     * Signing out is a server-side act here, not just "forget the token in the tab".
     *
     * The access token cannot be revoked — it is a signature over a claim set, valid until
     * it expires. What can be revoked is the refresh token, so logout deletes every one the
     * user holds and clears the cookie. Worst case the old access token survives for the few
     * minutes left on its clock, and nothing can mint a new one.
     *
     * @param array{path?: string, domain?: string|null, secure?: bool, http_only?: bool, same_site?: string|null} $cookieSettings
     */
    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(
        #[CurrentUser] User $user,
        RefreshTokenManagerInterface $refreshTokenManager,
        #[Autowire('%gesdinet_jwt_refresh_token.cookie%')] array $cookieSettings,
        #[Autowire('%gesdinet_jwt_refresh_token.token_parameter_name%')] string $cookieName,
    ): Response {
        if ($refreshTokenManager instanceof RevokeRefreshTokenManagerInterface) {
            $refreshTokenManager->revokeAllForUser($user);
        }

        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie(
            $cookieName,
            $cookieSettings['path'] ?? '/',
            $cookieSettings['domain'] ?? null,
            (bool) ($cookieSettings['secure'] ?? true),
            (bool) ($cookieSettings['http_only'] ?? true),
            $cookieSettings['same_site'] ?? Cookie::SAMESITE_LAX,
        );

        return $response;
    }
}
