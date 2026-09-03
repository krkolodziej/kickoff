<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ApiErrorResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a rejected bearer token answer in the project's envelope.
 *
 * Worth knowing, because it is not where you would look first: the bearer authenticator
 * does *not* go through a `failure_handler` service the way `json_login` does. Its
 * `onAuthenticationFailure()` builds a `JWTAuthenticationFailureResponse` itself, dispatches
 * one of the events below with that response attached, and returns whatever the event holds
 * afterwards. Replacing the response therefore means listening here — configuring a failure
 * handler on the firewall would have no effect at all.
 *
 * Expiry and invalidity are told apart on purpose. They call for different client
 * behaviour: `token_expired` means "refresh and retry", while `token_invalid` means the
 * credential is not going to become good again and the session should end.
 */
final class JwtFailureSubscriber
{
    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(new ApiErrorResponse(
            Response::HTTP_UNAUTHORIZED,
            'The access token has expired.',
            'token_expired',
        ));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse(new ApiErrorResponse(
            Response::HTTP_UNAUTHORIZED,
            'The access token is not valid.',
            'token_invalid',
        ));
    }

    /**
     * A request with no Authorization header at all. In practice the firewall's entry point
     * usually answers first, but the event exists and an unhandled one would leak Lexik's
     * shape, so it is covered here too.
     */
    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onMissing(JWTNotFoundEvent $event): void
    {
        $event->setResponse(new ApiErrorResponse(
            Response::HTTP_UNAUTHORIZED,
            'Authentication is required.',
            'authentication_required',
        ));
    }
}
