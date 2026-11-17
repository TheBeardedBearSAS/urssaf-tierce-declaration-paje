<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class NirTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidValues(): iterable
    {
        yield 'letters and digits' => ['Y' . str_repeat('1', 14)];
        yield 'any characters (no published pattern)' => [str_repeat('-', 15)];
        yield 'multibyte characters count as one' => [str_repeat('é', 15)];
    }

    #[DataProvider('provideValidValues')]
    public function testOfKeepsTheValue(string $value): void
    {
        static::assertSame($value, Nir::of($value)->value());
    }

    /**
     * @return iterable<string, array{string, string, array<string, int>}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'empty' => ['', 'nir.too_short', ['min_length' => 15]];
        yield 'one character short' => [str_repeat('1', 14), 'nir.too_short', ['min_length' => 15]];
        yield 'one character long' => [str_repeat('1', 16), 'nir.too_long', ['max_length' => 15]];
        yield 'multibyte one short' => [str_repeat('é', 14), 'nir.too_short', ['min_length' => 15]];
        yield 'multibyte one long' => [str_repeat('é', 16), 'nir.too_long', ['max_length' => 15]];
    }

    /**
     * @param array<string, int> $params
     */
    #[DataProvider('provideInvalidValues')]
    public function testOfRejectsAWrongLength(string $value, string $code, array $params): void
    {
        $exception = DomainAssertions::assertViolationCode($code, static fn(): Nir => Nir::of($value));

        static::assertSame($params, $exception->params());
    }

    public function testEquality(): void
    {
        $value = str_repeat('1', 15);

        static::assertTrue(Nir::of($value)->equals(Nir::of($value)));
        static::assertFalse(Nir::of($value)->equals(Nir::of(str_repeat('2', 15))));
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $value = 'Y' . str_repeat('7', 14);
        $dump = print_r(Nir::of($value), true);

        static::assertStringNotContainsString($value, $dump);
        static::assertStringContainsString('***', $dump);
    }
}
