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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class HalfDaysTest extends TestCase
{
    public function testFromHalfDays(): void
    {
        static::assertSame(0, HalfDays::fromHalfDays(0)->halfDays());
        static::assertSame(5, HalfDays::fromHalfDays(5)->halfDays());
    }

    public function testFromHalfDaysRejectsNegativeValues(): void
    {
        $this->expectExceptionObject(InvalidValue::negative('half_days'));

        HalfDays::fromHalfDays(-1);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideDecimalStrings(): iterable
    {
        yield 'zero' => ['0', 0];
        yield 'zero with decimal' => ['0.0', 0];
        yield 'half day' => ['0.5', 1];
        yield 'integer' => ['2', 4];
        yield 'integer with decimal' => ['2.0', 4];
        yield 'half' => ['2.5', 5];
        yield 'maximum integer digits' => ['999999999', 1_999_999_998];
    }

    #[DataProvider('provideDecimalStrings')]
    public function testFromDecimalString(string $days, int $halfDays): void
    {
        static::assertSame($halfDays, HalfDays::fromDecimalString($days)->halfDays());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDecimalStrings(): iterable
    {
        yield 'two decimals' => ['2.25', 'half_days.too_many_decimals'];
        yield 'two decimals even zeros' => ['2.00', 'half_days.too_many_decimals'];
        yield 'tenth 1' => ['2.1', 'half_days.not_multiple_of'];
        yield 'tenth 3' => ['2.3', 'half_days.not_multiple_of'];
        yield 'tenth 4' => ['2.4', 'half_days.not_multiple_of'];
        yield 'tenth 6' => ['2.6', 'half_days.not_multiple_of'];
        yield 'tenth 9' => ['0.9', 'half_days.not_multiple_of'];
        yield 'negative' => ['-1', 'half_days.negative'];
        yield 'comma' => ['1,5', 'half_days.invalid_format'];
        yield 'empty' => ['', 'half_days.invalid_format'];
        yield 'too many integer digits' => ['1000000000', 'half_days.too_large'];
    }

    #[DataProvider('provideInvalidDecimalStrings')]
    public function testFromDecimalStringRejectsInvalidInput(string $days, string $code): void
    {
        $exception = DomainAssertions::assertViolationCode($code, static fn(): HalfDays => HalfDays::fromDecimalString($days));

        static::assertSame($code, $exception->violationCode());
    }

    public function testNotMultipleOfDetails(): void
    {
        $this->expectExceptionObject(InvalidValue::notMultipleOf('half_days', '0.5'));

        HalfDays::fromDecimalString('2.3');
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideHalfDays(): iterable
    {
        yield 'zero' => [0, '0'];
        yield 'half day' => [1, '0.5'];
        yield 'one day' => [2, '1'];
        yield 'one and a half' => [3, '1.5'];
        yield 'two days' => [4, '2'];
        yield 'two and a half' => [5, '2.5'];
        yield 'large odd' => [PHP_INT_MAX, '4611686018427387903.5'];
    }

    #[DataProvider('provideHalfDays')]
    public function testToDecimalString(int $halfDays, string $expected): void
    {
        static::assertSame($expected, HalfDays::fromHalfDays($halfDays)->toDecimalString());
    }
}
