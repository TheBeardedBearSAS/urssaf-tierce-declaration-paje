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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class AmountTest extends TestCase
{
    public function testFromCentsKeepsAnyInteger(): void
    {
        static::assertSame(0, Amount::fromCents(0)->cents());
        static::assertSame(1, Amount::fromCents(1)->cents());
        static::assertSame(-1, Amount::fromCents(-1)->cents());
        static::assertSame(PHP_INT_MAX, Amount::fromCents(PHP_INT_MAX)->cents());
        static::assertSame(PHP_INT_MIN, Amount::fromCents(PHP_INT_MIN)->cents());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideDecimalStrings(): iterable
    {
        yield 'zero' => ['0', 0];
        yield 'negative zero' => ['-0', 0];
        yield 'integer' => ['10', 1000];
        yield 'one decimal' => ['63.1', 6310];
        yield 'two decimals' => ['20.09', 2009];
        yield 'trailing zero decimals' => ['0.50', 50];
        yield 'cents only' => ['0.01', 1];
        yield 'contract maximum' => ['9999.99', 999_999];
        yield 'negative integer' => ['-10', -1000];
        yield 'negative cents' => ['-0.50', -50];
        yield 'negative one cent' => ['-0.01', -1];
        yield 'maximum integer digits' => ['999999999999999', 99_999_999_999_999_900];
        yield 'negative maximum integer digits' => ['-999999999999999', -99_999_999_999_999_900];
    }

    #[DataProvider('provideDecimalStrings')]
    public function testFromDecimalString(string $amount, int $cents): void
    {
        static::assertSame($cents, Amount::fromDecimalString($amount)->cents());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDecimalStrings(): iterable
    {
        yield 'three decimals' => ['1.001', 'amount.too_many_decimals'];
        yield 'negative three decimals' => ['-1.001', 'amount.too_many_decimals'];
        yield 'comma' => ['1,00', 'amount.invalid_format'];
        yield 'leading space' => [' 1', 'amount.invalid_format'];
        yield 'trailing newline' => ["1\n", 'amount.invalid_format'];
        yield 'plus sign' => ['+1', 'amount.invalid_format'];
        yield 'exponent' => ['1e3', 'amount.invalid_format'];
        yield 'leading zero' => ['01', 'amount.invalid_format'];
        yield 'dot without decimals' => ['1.', 'amount.invalid_format'];
        yield 'empty' => ['', 'amount.invalid_format'];
        yield 'sign only' => ['-', 'amount.invalid_format'];
        yield 'too many integer digits' => ['1000000000000000', 'amount.too_large'];
        yield 'negative too many integer digits' => ['-1000000000000000', 'amount.too_large'];
    }

    #[DataProvider('provideInvalidDecimalStrings')]
    public function testFromDecimalStringRejectsInvalidInput(string $amount, string $code): void
    {
        $exception = DomainAssertions::assertViolationCode($code, static fn(): Amount => Amount::fromDecimalString($amount));

        static::assertSame($code, $exception->violationCode());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideCents(): iterable
    {
        yield 'zero' => [0, '0.00'];
        yield 'one cent' => [1, '0.01'];
        yield 'fifty cents' => [50, '0.50'];
        yield 'one euro' => [100, '1.00'];
        yield 'twelve fifty' => [1250, '12.50'];
        yield 'maximum' => [999_999, '9999.99'];
        yield 'minus one cent' => [-1, '-0.01'];
        yield 'minus fifty cents' => [-50, '-0.50'];
        yield 'minus twelve fifty' => [-1250, '-12.50'];
    }

    #[DataProvider('provideCents')]
    public function testToDecimalString(int $cents, string $expected): void
    {
        static::assertSame($expected, Amount::fromCents($cents)->toDecimalString());
        static::assertSame($cents, Amount::fromDecimalString($expected)->cents());
    }
}
