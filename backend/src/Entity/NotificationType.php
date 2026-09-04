<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * What a notification is about.
 *
 * Stored as a string rather than rendered into the row's text, so the client can pick an icon
 * and so a later stage can add a per-type preference without a migration that reparses prose.
 */
enum NotificationType: string
{
    case MatchFinished = 'MATCH_FINISHED';
    case KickOffReminder = 'KICK_OFF_REMINDER';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
