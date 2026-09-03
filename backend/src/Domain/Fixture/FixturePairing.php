<?php

declare(strict_types=1);

namespace App\Domain\Fixture;

/**
 * One meeting, as plain numbers.
 *
 * Deliberately not an entity and deliberately not holding `Team` objects. The scheduler
 * works in team *ids* so it can be tested with `[1, 2, 3, 4]` and no database at all —
 * which is what makes its whole test file run in milliseconds.
 */
final readonly class FixturePairing
{
    public function __construct(
        public int $roundNumber,
        public int $leg,
        public int $homeTeamId,
        public int $awayTeamId,
    ) {
    }

    public function reversed(int $roundNumber): self
    {
        return new self($roundNumber, 2, $this->awayTeamId, $this->homeTeamId);
    }

    /** Order-independent identity, for asserting that a pair meets exactly once. */
    public function pairKey(): string
    {
        $ids = [$this->homeTeamId, $this->awayTeamId];
        sort($ids);

        return $ids[0].'-'.$ids[1];
    }
}
