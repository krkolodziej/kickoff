<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\MatchEvent;
use App\Entity\MatchEventType;

final readonly class MatchEventResource
{
    public function __construct(
        public int $id,
        public int $fixtureId,
        public MatchEventType $type,
        public int $minute,
        public int $teamId,
        public bool $home,
        public int $playerId,
        public string $playerName,
        public ?int $relatedPlayerId,
        public ?string $relatedPlayerName,
    ) {
    }

    public static function fromEntity(MatchEvent $event): self
    {
        $related = $event->getRelatedPlayer();

        return new self(
            id: (int) $event->getId(),
            fixtureId: (int) $event->getFixture()->getId(),
            type: $event->getType(),
            minute: $event->getMinute(),
            teamId: (int) $event->getTeam()->getId(),
            // Which side of the timeline to draw it on. Derived here so the client does not
            // have to compare ids it would otherwise have to fetch.
            home: $event->getFixture()->isHomeTeam($event->getTeam()),
            playerId: (int) $event->getPlayer()->getId(),
            playerName: $event->getPlayer()->getFullName(),
            relatedPlayerId: null === $related ? null : (int) $related->getId(),
            relatedPlayerName: $related?->getFullName(),
        );
    }
}
