<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class StreetLetterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideValidLetters(): iterable
    {
        yield 'A' => ['A', true];
        yield 'B' => ['B', true];
        yield 'Z' => ['Z', true];
        yield 'apostrophe (pattern only)' => ["'", false];
        yield 'hyphen (pattern only)' => ['-', false];
        yield 'space (pattern only)' => [' ', false];
    }

    #[DataProvider('provideValidLetters')]
    public function testStreetLetterAcceptsThePattern(string $value, bool $documented): void
    {
        $letter = StreetLetter::of($value);

        static::assertSame($value, $letter->value());
        static::assertSame($documented, $letter->isDocumented());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidLetters(): iterable
    {
        yield 'empty' => ['', 'street_letter.too_short'];
        yield 'two letters' => ['AB', 'street_letter.too_long'];
        yield 'lower case' => ['a', 'street_letter.invalid_format'];
        yield 'digit' => ['1', 'street_letter.invalid_format'];
        yield 'accent' => ['É', 'street_letter.invalid_format'];
    }

    #[DataProvider('provideInvalidLetters')]
    public function testStreetLetterRejectsInvalidValues(string $value, string $code): void
    {
        static::assertSame(
            $code,
            DomainAssertions::assertViolationCode($code, static fn(): StreetLetter => StreetLetter::of($value))->violationCode(),
        );
    }

    public function testStreetLetterEquality(): void
    {
        static::assertTrue(StreetLetter::of('B')->equals(StreetLetter::of('B')));
        static::assertFalse(StreetLetter::of('B')->equals(StreetLetter::of('T')));
    }
}
