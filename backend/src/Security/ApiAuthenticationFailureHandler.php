<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Lexik and Gesdinet each ship their own JSON failure response, in their own shape.
 * Pointing both firewalls at this handler is what keeps a wrong password and a stale
 * refresh cookie looking like every other error this API produces.
 *
 * The message is deliberately vague. Distinguishing "no such account" from "wrong
 * password" would turn the login form into an account-enumeration oracle.
 */
final class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new ApiErrorResponse(
            Response::HTTP_UNAUTHORIZED,
            'Invalid credentials.',
            'invalid_credentials',
        );
    }
}
