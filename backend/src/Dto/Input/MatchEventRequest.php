<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\MatchEventType;
use Symfony\Component\Validator\Constraints as Assert;

final class MatchEventRequest
{
    public function __construct(
        /* A string on the wire, so an unknown value gets our message rather than the
           serializer's — the same reasoning as the organization role. */
        #[Assert\Choice(callback: [MatchEventType::class, 'values'], message: 'Choose a goal, a card or a substitution.')]
        public string $type = 'GOAL',
        /*
         * 180 covers ninety minutes, extra time and generous stoppage. Minute 0 is refused
         * because there is no minute 0 in football — the first minute is 1.
         */
        #[Assert\Range(min: 1, max: 180, notInRangeMessage: 'A minute is between {{ min }} and {{ max }}.')]
        public int $minute = 1,
        #[Assert\Positive(message: 'Choose the club.')]
        public int $teamId = 0,
        #[Assert\Positive(message: 'Choose the player.')]
        public int $playerId = 0,
        public ?int $relatedPlayerId = null,
    ) {
    }

    public function type(): MatchEventType
    {
        return MatchEventType::from($this->type);
    }
}
