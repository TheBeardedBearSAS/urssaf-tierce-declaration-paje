<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AmountEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class AmountEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{Amount, string}>
     */
    public static function provideEncodedAmounts(): iterable
    {
        yield 'zero' => [Amount::fromCents(0), '0.00'];
        yield 'one cent' => [Amount::fromCents(1), '0.01'];
        yield 'round euros' => [Amount::fromCents(1200), '12.00'];
        yield 'trailing zero' => [Amount::fromCents(1250), '12.50'];
        yield 'two decimals' => [Amount::fromCents(1102), '11.02'];
        yield 'contract maximum' => [Amount::fromCents(999_999), '9999.99'];
        yield 'negative' => [Amount::fromCents(-50), '-0.50'];
    }

    #[DataProvider('provideEncodedAmounts')]
    public function testEncodeAlwaysEmitsTwoDecimals(Amount $amount, string $literal): void
    {
        static::assertSame($literal, AmountEncoding::encode($amount)->literal());
        static::assertSame($literal, JsonEncoder::encode(AmountEncoding::encode($amount)));
    }

    /**
     * @return iterable<string, array{int|float|string, int}>
     */
    public static function provideDecodedAmounts(): iterable
    {
        yield 'integer zero' => [0, 0];
        yield 'integer' => [12, 1200];
        yield 'negative integer' => [-3, -300];
        yield 'float zero' => [0.0, 0];
        yield 'negative float zero' => [-0.0, 0];
        yield 'float round euros' => [12.0, 1200];
        yield 'float one decimal' => [12.5, 1250];
        yield 'float two decimals' => [11.02, 1102];
        yield 'float contract maximum' => [9999.99, 999_999];
        yield 'float one cent' => [0.01, 1];
        yield 'float negative' => [-0.5, -50];
        yield 'float with binary noise' => [0.1 + 0.2, 30];
        yield 'float rounded up' => [1.005, 101];
        yield 'float rounded down' => [2.004, 200];
        yield 'float rounded to zero' => [-0.001, 0];
        yield 'float above the contract maximum is not refused' => [123_456.78, 12_345_678];
        yield 'float largest supported' => [999_999_999_999_999.0, 99_999_999_999_999_900];
        yield 'integer largest supported' => [999_999_999_999_999, 99_999_999_999_999_900];
        yield 'digit string' => ['12', 1200];
        yield 'negative digit string' => ['-12', -1200];
        yield 'decimal string' => ['12.5', 1250];
    }

    #[DataProvider('provideDecodedAmounts')]
    public function testDecode(int|float|string $value, int $cents): void
    {
        static::assertSame($cents, AmountEncoding::decode($value, 'mntCotisations')->cents());
    }

    /**
     * @return iterable<string, array{int|float|string}>
     */
    public static function provideRefusedAmounts(): iterable
    {
        yield 'infinity' => [INF];
        yield 'negative infinity' => [-INF];
        yield 'not a number' => [NAN];
        yield 'float too large' => [1.0E+15];
        yield 'huge float' => [1.0E+300];
        yield 'integer too large' => [1_000_000_000_000_000];
        yield 'big integer digit string' => ['123456789012345678901234567890'];
        yield 'text' => ['abc'];
        yield 'empty string' => [''];
        yield 'too many decimals string' => ['1.234'];
        yield 'exponent string' => ['1e3'];
    }

    #[DataProvider('provideRefusedAmounts')]
    public function testDecodeRefusesWhatIsNotAnAmount(int|float|string $value): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "mntCotisations" is not a valid amount.',
            static fn(): Amount => AmountEncoding::decode($value, 'mntCotisations'),
        );
    }

    public function testTheCauseIsKeptWhenTheValueDoesNotFit(): void
    {
        $exception = WireAssertions::caught(static fn(): Amount => AmountEncoding::decode('abc', 'x'));
        static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());

        $exception = WireAssertions::caught(static fn(): Amount => AmountEncoding::decode(NAN, 'x'));
        static::assertNull($exception->getPrevious());
    }

    public function testDecodeOptionalKeepsAbsence(): void
    {
        static::assertNull(AmountEncoding::decodeOptional(null, 'x'));
        static::assertSame(0, AmountEncoding::decodeOptional(0, 'x')?->cents());
        static::assertSame(1250, AmountEncoding::decodeOptional(12.5, 'x')?->cents());
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "x" is not a valid amount.',
            static fn(): ?Amount => AmountEncoding::decodeOptional(INF, 'x'),
        );
    }

    public function testDecodeFromAJsonDocument(): void
    {
        $payload = JsonDecoder::decode('{"a":12.5,"b":3,"c":123456789012345678901234567890}');

        static::assertSame(1250, AmountEncoding::decode($payload->requireNumeric('a'), 'a')->cents());
        static::assertSame(300, AmountEncoding::decode($payload->requireNumeric('b'), 'b')->cents());
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "c" is not a valid amount.',
            static fn(): Amount => AmountEncoding::decode($payload->requireNumeric('c'), 'c'),
        );
    }
}
