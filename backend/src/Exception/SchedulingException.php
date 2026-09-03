<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

final class SchedulingException extends DomainException
{
    private function __construct(
        string $message,
        private readonly int $status,
        private readonly string $errorCode,
        array $fields = [],
    ) {
        parent::__construct($message, $fields);
    }

    /**
     * 409, not 422: the request is fine, the season's state is not. Two clubs is the minimum
     * for anything to be scheduled at all.
     */
    public static function notEnoughClubs(): self
    {
        return new self(
            'At least two clubs have to be registered before a calendar can be generated.',
            Response::HTTP_CONFLICT,
            'not_enough_clubs',
        );
    }

    public static function duplicateClubs(): self
    {
        return new self(
            'The same club appears twice in the registration list.',
            Response::HTTP_CONFLICT,
            'duplicate_clubs',
        );
    }

    /**
     * The one that matters. Generating twice would silently double every fixture, and the
     * results already recorded against the first set would be left pointing at half a
     * calendar.
     */
    public static function alreadyGenerated(): self
    {
        return new self(
            'This season already has a calendar. Delete it before generating another.',
            Response::HTTP_CONFLICT,
            'fixtures_already_generated',
        );
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
