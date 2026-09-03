<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * An `order` parameter naming a field that cannot be sorted on.
 *
 * 400 with the allowed list, rather than silently ignoring it. A sort that quietly does
 * nothing is the kind of bug that survives to production, because the response still looks
 * plausible — just in the wrong order.
 */
final class InvalidOrderingException extends DomainException
{
    /**
     * @param list<string> $allowed
     */
    public function __construct(string $field, array $allowed)
    {
        parent::__construct(
            \sprintf('Cannot order by "%s". Try one of: %s.', $field, implode(', ', $allowed)),
            ['order' => [\sprintf('Unsupported ordering field "%s".', $field)]],
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'invalid_ordering';
    }
}
