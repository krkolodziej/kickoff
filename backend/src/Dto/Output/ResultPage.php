<?php

declare(strict_types=1);

namespace App\Dto\Output;

/**
 * The paginated envelope.
 *
 * `next` and `previous` are page numbers rather than absolute URLs. DRF emits URLs, and it
 * is a reasonable choice for an API meant to be crawled — but it bakes the public host name
 * into every response, which then has to be right behind a proxy, in tests, and in a
 * container. A client that already knows the endpoint can add `?page=`.
 *
 * @template T
 */
final readonly class ResultPage
{
    /**
     * @param list<T> $results
     */
    public function __construct(
        public int $count,
        public int $page,
        public int $pageSize,
        public ?int $next,
        public ?int $previous,
        public array $results,
    ) {
    }
}
