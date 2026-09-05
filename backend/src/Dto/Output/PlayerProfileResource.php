<?php

declare(strict_types=1);

namespace App\Dto\Output;

/**
 * Everything one player's page needs, in one response.
 *
 * @see \App\Domain\Player\PlayerProfile for how it is assembled and what it costs.
 */
final readonly class PlayerProfileResource
{
    /**
     * @param list<PlayerSeasonRow> $seasons newest first
     */
    public function __construct(
        public PlayerResource $player,
        public array $seasons,
    ) {
    }
}
