<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * A squad rule broken by a request that is otherwise well formed and permitted.
 *
 * 422 rather than 409, because these are all statements about the values submitted — this
 * player, this number — rather than about the state of the season. The field name travels
 * with it so the message lands under the input that caused it.
 */
final class SquadRuleException extends DomainException
{
    public static function forField(string $field, string $message): self
    {
        return new self($message, [$field => [$message]]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public function getErrorCode(): string
    {
        return 'squad_rule_violated';
    }
}
