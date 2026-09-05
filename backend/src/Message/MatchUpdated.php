<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Something about this match changed: it started, a goal went in, it finished.
 *
 * Dispatched from inside the transaction that made the change, for the reason set out in
 * MatchLifecycle: the Doctrine transport writes it on the same connection, so a change that is
 * rolled back never announces itself. That matters more here than for notifications, because
 * publishing to a hub is an HTTP call that **cannot be undone**. Sending it directly from the
 * service would put a goal on somebody's screen that the database then refused to keep.
 *
 * So the queue provides the "after commit, and only then" semantics that the hub cannot.
 */
final readonly class MatchUpdated
{
    public function __construct(public int $fixtureId)
    {
    }
}
