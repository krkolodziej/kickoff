<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Dto\Output\UserResource;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Lexik answers a successful login with `{"token": "..."}` and nothing else, which would
 * force the SPA to immediately ask "and who am I?" over a second round trip.
 *
 * Adding the user here is not just a saving. The access token is a bearer credential; the
 * client should treat it as opaque and never parse it for display data. Handing the profile
 * back beside it removes the temptation to decode the JWT in the browser.
 *
 * Gesdinet listens on this same event to attach the refresh cookie, which is what makes
 * `AuthenticationSuccessHandler` reusable from the register endpoint.
 */
// Lexik dispatches this under a string name, not under the event class, so listening
// by class would silently never fire.
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final class AuthenticationSuccessSubscriber
{
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $data['user'] = UserResource::fromEntity($user)->toArray();

        $event->setData($data);
    }
}
