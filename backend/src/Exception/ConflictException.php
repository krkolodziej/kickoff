<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * The request is well-formed and the caller is allowed to make it — but the current state
 * of the data forbids it. 409, not 422: nothing about the payload is wrong.
 */
class ConflictException extends DomainException
{
    /**
     * Named `errorCode`, not `code`: \Exception already has a `$code` property, and
     * redeclaring it as readonly is a fatal error at compile time.
     *
     * @param array<string, list<string>> $fields
     */
    public function __construct(
        string $message,
        private readonly string $errorCode = 'conflict',
        array $fields = [],
    ) {
        parent::__construct($message, $fields);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
