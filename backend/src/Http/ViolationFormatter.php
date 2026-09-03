<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Turns a ConstraintViolationList into the `fields` half of the error envelope.
 *
 * The subtle part is the key. A violation's property path is a PHP property name, so it is
 * camelCase (`passwordConfirm`), while every other name this API emits has already been
 * converted to snake_case by the serializer. Emitting the raw path would mean the frontend
 * looks up `fields.password_confirm`, finds nothing, and silently shows no error at all.
 */
final class ViolationFormatter
{
    private CamelCaseToSnakeCaseNameConverter $nameConverter;

    public function __construct()
    {
        $this->nameConverter = new CamelCaseToSnakeCaseNameConverter();
    }

    /**
     * @return array<string, list<string>>
     */
    public function format(ConstraintViolationListInterface $violations): array
    {
        $fields = [];

        foreach ($violations as $violation) {
            $path = $this->convertPath($violation->getPropertyPath());
            $fields[$path][] = (string) $violation->getMessage();
        }

        return $fields;
    }

    /**
     * `roster[0].shirtNumber` -> `roster[0].shirt_number`. A violation on the object itself
     * has an empty path; those are collected under `_` so nothing is dropped.
     */
    private function convertPath(string $propertyPath): string
    {
        if ('' === $propertyPath) {
            return '_';
        }

        $segments = preg_split('/(?=\[)|\./', $propertyPath, -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        $converted = array_map(
            fn (string $segment): string => str_starts_with($segment, '[')
                ? $segment
                : $this->nameConverter->normalize($segment),
            $segments,
        );

        return preg_replace('/\.(\[)/', '$1', implode('.', $converted)) ?? $propertyPath;
    }
}
