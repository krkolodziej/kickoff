<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Season;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Enough of a season to name it and to link to it.
 *
 * Three resources want to point at a season without being it — a league says which season is
 * its latest, a club says which one it is playing, a player's squad entry says which one he
 * was registered for. One small type rather than three near-identical inline shapes, and it
 * carries the league id because every path to a season goes through its league.
 */
final readonly class SeasonRefResource
{
    public function __construct(
        public int $id,
        public int $leagueId,
        public string $name,
        /* A date, not an instant — see SeasonResource for what that costs otherwise. */
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $startDate,
    ) {
    }

    public static function fromEntity(Season $season): self
    {
        return new self(
            id: (int) $season->getId(),
            // Free on an unloaded proxy: Doctrine keeps the identifier without a round trip.
            leagueId: (int) $season->getLeague()->getId(),
            name: $season->getName(),
            startDate: $season->getStartDate(),
        );
    }
}
