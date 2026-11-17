<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Decimal;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class DecimalParseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function provideValidInputs(): iterable
    {
        yield 'zero' => ['0', 2, 3, 0];
        yield 'integer' => ['12', 2, 3, 1200];
        yield 'one decimal' => ['12.5', 2, 3, 1250];
        yield 'max decimals' => ['12.05', 2, 3, 1205];
        yield 'zero with decimals' => ['0.07', 2, 3, 7];
        yield 'max integer digits' => ['999', 2, 3, 99_900];
        yield 'three decimals scale' => ['1.75', 3, 3, 1750];
    }

    #[DataProvider('provideValidInputs')]
    public function testParse(string $value, int $decimals, int $maxIntegerDigits, int $expected): void
    {
        static::assertSame($expected, Decimal::parse($value, 'money', $decimals, $maxIntegerDigits));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidInputs(): iterable
    {
        yield 'empty' => ['', 'money.invalid_format'];
        yield 'leading zero' => ['01', 'money.invalid_format'];
        yield 'comma' => ['1,5', 'money.invalid_format'];
        yield 'dot only' => ['1.', 'money.invalid_format'];
        yield 'no integer part' => ['.5', 'money.invalid_format'];
        yield 'plus sign' => ['+1', 'money.invalid_format'];
        yield 'exponent' => ['1e3', 'money.invalid_format'];
        yield 'leading space' => [' 1', 'money.invalid_format'];
        yield 'trailing space' => ['1 ', 'money.invalid_format'];
        yield 'trailing newline' => ["1\n", 'money.invalid_format'];
        yield 'letters' => ['abc', 'money.invalid_format'];
        yield 'negative' => ['-1.5', 'money.negative'];
        yield 'too many decimals' => ['1.005', 'money.too_many_decimals'];
        yield 'too many decimals even zeros' => ['1.000', 'money.too_many_decimals'];
        yield 'too many integer digits' => ['1000', 'money.too_large'];
    }

    #[DataProvider('provideInvalidInputs')]
    public function testParseRejectsInvalidInput(string $value, string $code): void
    {
        try {
            Decimal::parse($value, 'money', 2, 3);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testTooManyDecimalsReportsTheLimit(): void
    {
        try {
            Decimal::parse('1.1234', 'quantity', 3, 3);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame(['max_decimals' => 3], $exception->params());
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideSignedInputs(): iterable
    {
        yield 'positive' => ['12.5', 1250];
        yield 'negative' => ['-12.5', -1250];
        yield 'negative cents' => ['-0.05', -5];
        yield 'negative zero' => ['-0', 0];
        yield 'negative zero with decimals' => ['-0.00', 0];
    }

    #[DataProvider('provideSignedInputs')]
    public function testParseSigned(string $value, int $expected): void
    {
        static::assertSame($expected, Decimal::parseSigned($value, 'amount', 2, 3));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidSignedInputs(): iterable
    {
        yield 'empty' => ['', 'amount.invalid_format'];
        yield 'double sign' => ['--1', 'amount.invalid_format'];
        yield 'plus sign' => ['+1', 'amount.invalid_format'];
        yield 'sign only' => ['-', 'amount.invalid_format'];
        yield 'negative leading zero' => ['-01', 'amount.invalid_format'];
        yield 'trailing newline' => ["-1\n", 'amount.invalid_format'];
        yield 'negative too many decimals' => ['-1.005', 'amount.too_many_decimals'];
        yield 'negative too large' => ['-1000', 'amount.too_large'];
    }

    #[DataProvider('provideInvalidSignedInputs')]
    public function testParseSignedRejectsInvalidInput(string $value, string $code): void
    {
        static::assertSame($code, DomainAssertions::assertViolationCode($code, static fn(): int => Decimal::parseSigned($value, 'amount', 2, 3))->violationCode());
    }

    public function testMaximumIntegerDigitsBoundary(): void
    {
        static::assertSame(99_900, Decimal::parseSigned('999', 'amount', 2, 3));
        static::assertSame(-99_999, Decimal::parseSigned('-999.99', 'amount', 2, 3));
        static::assertSame('amount.too_large', DomainAssertions::assertViolationCode('amount.too_large', static fn(): int => Decimal::parseSigned('1000', 'amount', 2, 3))->violationCode());
    }
}
