<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Entity\Fixture;

/**
 * The one place that decides what a match's topic is called.
 *
 * A topic is a string that both ends have to agree on: the publisher writes it, the subscriber
 * asks for it, and the token that authorises the subscription names it. Three agreements about
 * one string is exactly the sort of thing that drifts, so it is computed here and nowhere else.
 */
final class MatchTopic
{
    public static function for(Fixture $fixture): string
    {
        return self::forId((int) $fixture->getId());
    }

    public static function forId(int $fixtureId): string
    {
        return '/matches/'.$fixtureId;
    }
}
