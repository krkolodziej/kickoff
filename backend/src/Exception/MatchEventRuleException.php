<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * An event that breaks a rule about *who* or *when*.
 *
 * 422 rather than 409, because these are statements about the values submitted — this player,
 * this club, this minute — with one exception: recording anything at all on a match that is
 * not live is about the match's state, and that one is an InvalidTransitionException's
 * sibling. It is kept here as a 409 for the same reason.
 */
final class MatchEventRuleException extends DomainException
{
    private function __construct(
        string $message,
        private readonly int $status,
        private readonly string $errorCode,
        array $fields = [],
    ) {
        parent::__construct($message, $fields);
    }

    public static function notLive(string $status): self
    {
        return new self(
            \sprintf('Events can only be recorded while a match is live. This one is %s.', strtolower($status)),
            Response::HTTP_CONFLICT,
            'match_not_live',
        );
    }

    public static function forField(string $field, string $message): self
    {
        return new self($message, Response::HTTP_UNPROCESSABLE_ENTITY, 'match_event_rule_violated', [$field => [$message]]);
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
