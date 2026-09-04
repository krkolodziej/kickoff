<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A match has been played to the end.
 *
 * The payload is an **id**, not a `Fixture`. That is the rule for every message here and it
 * is not stylistic:
 *
 * - The message is serialised into a database row and read back later, possibly by a worker
 *   that started after the process which sent it had exited. A Doctrine entity carries proxies
 *   and an identity map that mean nothing in that other process.
 * - By the time the message is handled the row may have changed, or been deleted. Carrying a
 *   snapshot would let a handler act on a match that no longer looks like that, silently.
 *
 * So the message says what happened and which row it happened to; the handler reads the truth
 * for itself, and copes with the row having gone.
 */
final readonly class MatchFinished
{
    public function __construct(public int $fixtureId)
    {
    }
}
