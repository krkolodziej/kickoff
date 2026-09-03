<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Implemented by exceptions the domain throws deliberately, so that a controller never
 * needs a try/catch to turn a rule violation into the right status code.
 *
 * Everything else that escapes is a 500 by definition: a bug, not a decision.
 */
interface ApiExceptionInterface extends \Throwable
{
    public function getStatusCode(): int;

    /** Stable machine-readable identifier, snake_case, e.g. `fixtures_already_generated`. */
    public function getErrorCode(): string;

    /** @return array<string, list<string>> */
    public function getFields(): array;
}
