<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * What the firewall does when an anonymous request reaches a protected endpoint.
 *
 * A browser-facing application would redirect to a login page here. An API must not: it
 * answers 401 in the same envelope as everything else. It must also never send a
 * `WWW-Authenticate: Basic` header, because that makes the browser open its own credentials
 * dialog on top of the SPA, which looks exactly like the application having frozen.
 */
final class ApiEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new ApiErrorResponse(
            Response::HTTP_UNAUTHORIZED,
            'Authentication is required.',
            'authentication_required',
        );
    }
}
