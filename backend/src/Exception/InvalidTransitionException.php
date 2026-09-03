<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\MatchStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * A transition the machine does not allow.
 *
 * **409, not 422.** Nothing about the request is malformed — finishing a match is a perfectly
 * good thing to ask for, just not for a match that has not started. The distinction is worth
 * holding to: 422 says "fix your payload", 409 says "the world is not in that state".
 *
 * The message names what *is* allowed, so a client that has drifted out of step with the
 * server can recover without guessing.
 */
final class InvalidTransitionException extends DomainException
{
    public function __construct(MatchStatus $from, MatchStatus $to)
    {
        $allowed = $from->allowedTransitionValues();

        parent::__construct(
            [] === $allowed
                ? \sprintf('A %s match cannot change state.', strtolower($from->value))
                : \sprintf(
                    'Cannot go from %s to %s. Allowed from here: %s.',
                    strtolower($from->value),
                    strtolower($to->value),
                    implode(', ', array_map('strtolower', $allowed)),
                ),
            ['status' => [\sprintf('Not allowed from %s.', strtolower($from->value))]],
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function getErrorCode(): string
    {
        return 'invalid_transition';
    }
}
