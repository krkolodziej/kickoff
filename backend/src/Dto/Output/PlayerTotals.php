<?php

declare(strict_types=1);

namespace App\Dto\Output;

/**
 * What a player did, counted.
 *
 * Never serialized on its own — it is what a batched aggregate query carries back so that a
 * page of players can be built without a query per row. {@see PlayerResource} unpacks it.
 *
 * There is deliberately no appearances count. Nothing in this application records who took
 * the field, and counting matches-a-player-has-an-event-in would report a defender who never
 * scored as having played nothing. {@see PlayerStatisticsRow} makes the same argument at
 * greater length.
 */
final readonly class PlayerTotals
{
    public function __construct(
        public int $playerId,
        public int $goals,
        public int $yellowCards,
        public int $redCards,
    ) {
    }
}
