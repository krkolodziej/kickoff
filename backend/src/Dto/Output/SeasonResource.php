<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Season;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class SeasonResource
{
    public function __construct(
        public int $id,
        public int $leagueId,
        public string $name,
        /**
         * A calendar date, not an instant.
         *
         * Without this the serializer emits RFC 3339 — "2026-08-15T00:00:00+02:00" — which invents a
         * midnight and a timezone that the value does not have. Worse than ugly: a client in another
         * zone can shift it to the day before.
         */
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $startDate,
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public ?\DateTimeImmutable $endDate,
        /* createdAt is an instant, so it keeps the full RFC 3339 form. */
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(Season $season): self
    {
        return new self(
            id: (int) $season->getId(),
            leagueId: (int) $season->getLeague()->getId(),
            name: $season->getName(),
            startDate: $season->getStartDate(),
            endDate: $season->getEndDate(),
            createdAt: $season->getCreatedAt(),
        );
    }
}
