<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Lets a client ask "has this changed?" and be told "no" in a couple of hundred bytes.
 *
 * The screens that benefit are the ones already asking repeatedly: a league table refetched
 * when a tab regains focus, a calendar of 132 fixtures, a match being polled every three
 * seconds while it is live. None of those change between most of their requests, and an
 * unchanged answer costs a status line instead of a payload.
 *
 * The tag is computed from the body that was going to be sent, not from anything about the
 * data behind it. That is the weak version of caching — the server still does all the work
 * and only saves the transfer — and it is deliberately the version chosen here: a tag derived
 * from "when did this season last change" needs something to keep that timestamp accurate,
 * and a stale one serves a wrong answer with confidence. This cannot go wrong: if the bytes
 * differ, the tag differs.
 */
final class ETagSubscriber
{
    #[AsEventListener(event: ResponseEvent::class, priority: -64)]
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$this->applies($request, $response)) {
            return;
        }

        $response->setEtag(hash('xxh3', (string) $response->getContent()));

        // `private` because these are answers about one person's organizations, and a shared
        // cache holding them would be a data leak rather than an optimisation.
        $response->setPrivate();

        // Turns a matching If-None-Match into 304 with an empty body, and leaves the full
        // response alone otherwise.
        $response->isNotModified($request);
    }

    private function applies(Request $request, Response $response): bool
    {
        // Only reads. A POST that answered 200 is reporting something that just happened, and
        // a client asking whether it changed is asking the wrong question.
        if (!$request->isMethodCacheable()) {
            return false;
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return false;
        }

        // Anything that sets a cookie is doing something on this request in particular — the
        // realtime token endpoint, for one — and a 304 would drop the header it exists for.
        if ([] !== $response->headers->getCookies()) {
            return false;
        }

        return str_starts_with((string) $request->attributes->get('_route'), 'api_');
    }
}
