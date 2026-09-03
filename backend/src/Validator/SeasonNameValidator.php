<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class SeasonNameValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SeasonName) {
            throw new UnexpectedTypeException($constraint, SeasonName::class);
        }

        // Emptiness is somebody else's job. A validator that also enforces "required" cannot
        // be used on an optional field, and every constraint in Symfony works this way.
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (1 !== preg_match('/^(\d{4})(?:\/(\d{2}|\d{4}))?$/', $value, $matches)) {
            $this->context->buildViolation($constraint->shapeMessage)->addViolation();

            return;
        }

        if (!isset($matches[2])) {
            return;
        }

        $first = (int) $matches[1];

        // "2026/27" and "2026/2027" are both written in practice, so both are accepted and
        // compared the same way.
        if (2 === \strlen($matches[2])) {
            $second = intdiv($first, 100) * 100 + (int) $matches[2];

            // 2099/00 is the 2099-2100 season, not 2099-2000. Assuming the first year's
            // century silently breaks once every hundred years, which is exactly the kind of
            // thing nobody notices until it happens.
            if ($second < $first) {
                $second += 100;
            }
        } else {
            $second = (int) $matches[2];
        }

        if ($second === $first + 1) {
            return;
        }

        $expected = $first + 1;

        $this->context->buildViolation($constraint->sequenceMessage)
            ->setParameter('{{ expected }}', \sprintf('%d/%02d', $first, $expected % 100))
            ->setParameter('{{ given }}', $value)
            ->addViolation();
    }
}
