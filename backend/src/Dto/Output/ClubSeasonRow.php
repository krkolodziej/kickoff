<?php

declare(strict_types=1);

namespace App\Dto\Output;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * One line of a club's history: a season, the squad it fielded and how that season went.
 *
 * `position` is nullable and usually null, which is deliberate. A club's own results can be
 * aggregated from its own fixtures, but where it *finished* is a fact about the whole league,
 * so it needs the entire table for that season computed. That is worth three queries for the
 * season being played and is not worth three queries per season of history.
 */
final readonly class ClubSeasonRow
{
    public function __construct(
        public int $seasonId,
        public string $seasonName,
        public int $leagueId,
        public string $leagueName,
        /* A date, not an instant — see SeasonResource for what that costs otherwise. */
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $startDate,
        public int $squadSize,
        public int $played,
        public int $won,
        public int $drawn,
        public int $lost,
        public int $goalsFor,
        public int $goalsAgainst,
        public int $goalDifference,
        public int $points,
        public ?int $position,
    ) {
    }
}
