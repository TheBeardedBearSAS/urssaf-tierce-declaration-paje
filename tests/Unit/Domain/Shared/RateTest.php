<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class RateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideValidRates(): iterable
    {
        yield 'zero' => ['0', '0'];
        yield 'zero with decimals' => ['0.0', '0'];
        yield 'negative zero' => ['-0', '0'];
        yield 'negative zero with decimals' => ['-0.00', '0'];
        yield 'many zeros' => ['000', '0'];
        yield 'integer' => ['7', '7'];
        yield 'leading zeros' => ['007', '7'];
        yield 'leading zero before point' => ['00.5', '0.5'];
        yield 'decimals' => ['7.5', '7.5'];
        yield 'trailing zeros trimmed' => ['7.500', '7.5'];
        yield 'point trimmed' => ['7.000', '7'];
        yield 'inner zeros kept' => ['10.05', '10.05'];
        yield 'integer trailing zeros kept' => ['100', '100'];
        yield 'long precision' => ['12.345678901234567', '12.345678901234567'];
        yield 'negative' => ['-1.5', '-1.5'];
        yield 'negative leading zeros' => ['-007.50', '-7.5'];
        yield 'negative fraction only' => ['-0.01', '-0.01'];
        yield 'php small float' => ['1.0E-5', '0.00001'];
        yield 'negative exponent one place' => ['5E-1', '0.5'];
        yield 'negative exponent inside digits' => ['12.5e-1', '1.25'];
        yield 'negative exponent exactly at the point' => ['12e-2', '0.12'];
        yield 'negative exponent past the integer part' => ['1.5E-3', '0.0015'];
        yield 'positive exponent inside the fraction' => ['1.234e1', '12.34'];
        yield 'positive exponent consuming the fraction' => ['1.5e1', '15'];
        yield 'positive exponent padding zeros' => ['1.5e2', '150'];
        yield 'explicit plus exponent' => ['2E+3', '2000'];
        yield 'zero exponent' => ['7.50e0', '7.5'];
        yield 'zero mantissa' => ['0e5', '0'];
        yield 'negative mantissa' => ['-1.5E-2', '-0.015'];
        yield 'negative zero mantissa' => ['-0e-2', '0'];
        yield 'three digit exponent' => ['1e-100', '0.' . str_repeat('0', 99) . '1'];
    }

    #[DataProvider('provideValidRates')]
    public function testFromString(string $value, string $canonical): void
    {
        static::assertSame($canonical, Rate::fromString($value)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidRates(): iterable
    {
        yield 'empty' => [''];
        yield 'comma' => ['1,5'];
        yield 'plus sign' => ['+1'];
        yield 'exponent without digits' => ['1e'];
        yield 'exponent sign only' => ['1e-'];
        yield 'exponent too long' => ['1e1000'];
        yield 'exponent with fraction' => ['1e1.5'];
        yield 'exponent without mantissa' => ['e5'];
        yield 'trailing newline after exponent' => ["1e5\n"];
        yield 'dot without decimals' => ['1.'];
        yield 'no integer part' => ['.5'];
        yield 'sign only' => ['-'];
        yield 'double sign' => ['--1'];
        yield 'letters' => ['abc'];
        yield 'leading space' => [' 1'];
        yield 'trailing space' => ['1 '];
        yield 'trailing newline' => ["1\n"];
        yield 'two points' => ['1.2.3'];
        yield 'percent' => ['7%'];
    }

    #[DataProvider('provideInvalidRates')]
    public function testFromStringRejectsInvalidInput(string $value): void
    {
        $exception = DomainAssertions::assertViolationCode('rate.invalid_format', static fn(): Rate => Rate::fromString($value));

        static::assertSame(['expected_format' => 'decimal number with "." as separator'], $exception->params());
    }

    public function testEquality(): void
    {
        static::assertTrue(Rate::fromString('7.50')->equals(Rate::fromString('7.5')));
        static::assertTrue(Rate::fromString('-0')->equals(Rate::fromString('0')));
        static::assertFalse(Rate::fromString('7.5')->equals(Rate::fromString('7.6')));
        static::assertFalse(Rate::fromString('7.5')->equals(Rate::fromString('-7.5')));
    }
}
