<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class PajeEmployerNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidValues(): iterable
    {
        yield 'letters and digits' => ['Y' . str_repeat('1', 13)];
        yield 'any characters (no published pattern)' => [str_repeat('-', 14)];
        yield 'multibyte characters count as one' => [str_repeat('é', 14)];
    }

    #[DataProvider('provideValidValues')]
    public function testOfKeepsTheValue(string $value): void
    {
        static::assertSame($value, PajeEmployerNumber::of($value)->value());
    }

    /**
     * @return iterable<string, array{string, string, array<string, int>}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'empty' => ['', 'employer_number.too_short', ['min_length' => 14]];
        yield 'one character short' => [str_repeat('1', 13), 'employer_number.too_short', ['min_length' => 14]];
        yield 'one character long' => [str_repeat('1', 15), 'employer_number.too_long', ['max_length' => 14]];
        yield 'multibyte one short' => [str_repeat('é', 13), 'employer_number.too_short', ['min_length' => 14]];
        yield 'multibyte one long' => [str_repeat('é', 15), 'employer_number.too_long', ['max_length' => 14]];
    }

    /**
     * @param array<string, int> $params
     */
    #[DataProvider('provideInvalidValues')]
    public function testOfRejectsAWrongLength(string $value, string $code, array $params): void
    {
        $exception = DomainAssertions::assertViolationCode($code, static fn(): PajeEmployerNumber => PajeEmployerNumber::of($value));

        static::assertSame($params, $exception->params());
    }

    public function testEquality(): void
    {
        $value = str_repeat('1', 14);

        static::assertTrue(PajeEmployerNumber::of($value)->equals(PajeEmployerNumber::of($value)));
        static::assertFalse(PajeEmployerNumber::of($value)->equals(PajeEmployerNumber::of(str_repeat('2', 14))));
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $value = 'Y' . str_repeat('7', 13);
        $dump = print_r(PajeEmployerNumber::of($value), true);

        static::assertStringNotContainsString($value, $dump);
        static::assertStringContainsString('***', $dump);
    }
}
