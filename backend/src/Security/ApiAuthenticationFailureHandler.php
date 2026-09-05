<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Lexik and Gesdinet each ship their own JSON failure response, in their own shape.
 * Pointing both firewalls at this handler is what keeps a wrong password and a stale
 * refresh cookie looking like every other error this API produces.
 *
 * The message is deliberately vague. Distinguishing "no such account" from "wrong
 * password" would turn the login form into an account-enumeration oracle.
 *
 * Being throttled is the one failure worth telling apart, and it leaks nothing: it says
 * something about how often this address has tried, not about whether any account exists.
 * Without it a rate-limited caller sees "invalid credentials" and concludes the password is
 * wrong — so a person retypes a password that was right, and a client keeps hammering.
 */
final class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new ApiErrorResponse(
                Response::HTTP_TOO_MANY_REQUESTS,
                'Too many sign-in attempts. Wait a minute and try again.',
                'too_many_attempts',
            );
        }

        return new ApiErrorResponse(
            Response::HTTP_UNAUTHORIZED,
            'Invalid credentials.',
            'invalid_credentials',
        );
    }
}
