<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Base class for "you asked for something the rules do not allow".
 *
 * Subclasses pick their own status: 409 when the request is well-formed but the current
 * state forbids it, 422 when the payload itself is inconsistent with the data it refers to.
 */
abstract class DomainException extends \RuntimeException implements ApiExceptionInterface
{
    /**
     * @param array<string, list<string>> $fields
     */
    public function __construct(
        string $message,
        private readonly array $fields = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    abstract public function getStatusCode(): int;

    abstract public function getErrorCode(): string;

    public function getFields(): array
    {
        return $this->fields;
    }
}
