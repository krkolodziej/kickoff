<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Validator\SeasonName;
use App\Validator\SeasonNameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * ConstraintValidatorTestCase gives the validator a fake execution context, so the whole
 * thing runs without a kernel or a database — microseconds per case.
 *
 * @extends ConstraintValidatorTestCase<SeasonNameValidator>
 */
final class SeasonNameValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SeasonNameValidator
    {
        return new SeasonNameValidator();
    }

    #[DataProvider('acceptedNames')]
    public function testItAcceptsAWellFormedName(string $name): void
    {
        $this->validator->validate($name, new SeasonName());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedNames(): iterable
    {
        yield 'single year' => ['2026'];
        yield 'two digit second year' => ['2026/27'];
        yield 'four digit second year' => ['2026/2027'];
        yield 'across a century' => ['2099/00'];
    }

    /**
     * Emptiness is somebody else's constraint. A validator that also enforced "required"
     * could not be put on an optional field.
     */
    public function testItIgnoresEmptiness(): void
    {
        $this->validator->validate(null, new SeasonName());
        $this->validator->validate('', new SeasonName());

        $this->assertNoViolation();
    }

    #[DataProvider('malformedNames')]
    public function testItRejectsAMalformedName(string $name): void
    {
        $this->validator->validate($name, new SeasonName());

        $this->buildViolation('Name a season for one year ("2026") or two ("2026/27").')->assertRaised();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedNames(): iterable
    {
        yield 'words' => ['Spring'];
        yield 'two digits' => ['26'];
        yield 'three parts' => ['2026/27/28'];
        yield 'trailing slash' => ['2026/'];
    }

    /**
     * The reason this constraint exists. A regular expression can insist on the shape
     * `\d{4}/\d{2}` but cannot tell "2026/27" from "2026/29" — that needs arithmetic.
     */
    public function testItRejectsYearsThatDoNotFollowOneAnother(): void
    {
        $this->validator->validate('2026/29', new SeasonName());

        $this->buildViolation('The second year has to follow the first: "{{ expected }}", not "{{ given }}".')
            ->setParameter('{{ expected }}', '2026/27')
            ->setParameter('{{ given }}', '2026/29')
            ->assertRaised();
    }

    public function testItRejectsASecondYearThatGoesBackwards(): void
    {
        $this->validator->validate('2026/25', new SeasonName());

        $this->buildViolation('The second year has to follow the first: "{{ expected }}", not "{{ given }}".')
            ->setParameter('{{ expected }}', '2026/27')
            ->setParameter('{{ given }}', '2026/25')
            ->assertRaised();
    }
}
