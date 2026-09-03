<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ViolationFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * No kernel, no database. The formatter is a pure function over a violation list, so the
 * test costs microseconds and can be run on every keystroke.
 */
final class ViolationFormatterTest extends TestCase
{
    private ViolationFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ViolationFormatter();
    }

    public function testItConvertsPropertyPathsToTheWireCasing(): void
    {
        $result = $this->formatter->format($this->violations([
            'passwordConfirm' => 'The two passwords do not match.',
            'email' => 'Enter a valid email address.',
        ]));

        self::assertSame([
            'password_confirm' => ['The two passwords do not match.'],
            'email' => ['Enter a valid email address.'],
        ], $result);
    }

    public function testItGroupsSeveralMessagesUnderOneField(): void
    {
        $result = $this->formatter->format(new ConstraintViolationList([
            $this->violation('password', 'Choose a password.'),
            $this->violation('password', 'Use at least 8 characters.'),
        ]));

        self::assertSame(
            ['password' => ['Choose a password.', 'Use at least 8 characters.']],
            $result,
        );
    }

    public function testItKeepsCollectionIndicesIntact(): void
    {
        $result = $this->formatter->format($this->violations([
            'roster[0].shirtNumber' => 'Already taken.',
        ]));

        self::assertSame(['roster[0].shirt_number' => ['Already taken.']], $result);
    }

    public function testAViolationOnTheObjectItselfIsNotDropped(): void
    {
        $result = $this->formatter->format($this->violations([
            '' => 'An account with this email already exists.',
        ]));

        self::assertSame(['_' => ['An account with this email already exists.']], $result);
    }

    /**
     * @param array<string, string> $messagesByPath
     */
    private function violations(array $messagesByPath): ConstraintViolationList
    {
        $list = new ConstraintViolationList();

        foreach ($messagesByPath as $path => $message) {
            $list->add($this->violation($path, $message));
        }

        return $list;
    }

    private function violation(string $path, string $message): ConstraintViolation
    {
        return new ConstraintViolation($message, $message, [], null, $path, null);
    }
}
