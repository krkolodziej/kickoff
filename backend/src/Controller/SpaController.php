<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Hands the built single-page application to anything that is not the API.
 *
 * The SPA owns routes like `/dashboard` and `/organizations/3/leagues`, but those paths exist
 * only in the browser — asked for directly, by a refresh or a pasted link, they reach the
 * server, which has never heard of them. Without this they would 404, and the application
 * would work until somebody pressed F5.
 *
 * Deliberately last in the routing table (`priority: -1000`) and deliberately blind to
 * anything under `/api`, so a genuinely unknown endpoint still answers 404 in the JSON
 * envelope rather than quietly returning HTML to a client expecting JSON.
 */
final class SpaController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/index.html')]
        private readonly string $indexPath,
    ) {
    }

    #[Route(
        '/{path}',
        name: 'spa_index',
        requirements: ['path' => '^(?!api/|_(profiler|wdt)/|bundles/).*'],
        defaults: ['path' => ''],
        methods: ['GET'],
        priority: -1000,
    )]
    public function __invoke(): Response
    {
        if (!is_file($this->indexPath)) {
            // Only reachable when the API is run without a built SPA beside it, which is the
            // normal state during development — Vite serves the frontend on its own port and
            // proxies here. Saying so beats a bare 404 that looks like a routing bug.
            return new Response(
                'The API is running. The single-page application has not been built into public/.',
                Response::HTTP_NOT_FOUND,
                ['Content-Type' => 'text/plain; charset=utf-8'],
            );
        }

        $response = new Response((string) file_get_contents($this->indexPath));
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');

        // The hashed assets index.html points at are immutable and cached hard by the web
        // server; index.html itself must not be, or a deploy ships new assets that nobody's
        // browser ever asks for.
        $response->setCache(['no_cache' => true, 'no_store' => true, 'private' => true]);

        return $response;
    }
}
