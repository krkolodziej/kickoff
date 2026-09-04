<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A tick. "Go and look at the calendar.".
 *
 * It carries nothing, and that is right: the schedule's job is to say *when* to look, not
 * what to look at. Putting the window in the message would freeze it at the moment the
 * schedule was defined, so a message delayed by a busy worker would ask about a window that
 * had already passed. The handler works out "now" for itself.
 */
final readonly class SendKickOffReminders
{
}
