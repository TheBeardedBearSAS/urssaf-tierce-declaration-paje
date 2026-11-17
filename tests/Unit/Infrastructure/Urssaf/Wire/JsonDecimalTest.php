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
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecimal;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class JsonDecimalTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidLiterals(): iterable
    {
        yield 'zero' => ['0'];
        yield 'integer' => ['12'];
        yield 'decimal' => ['11.02'];
        yield 'zero decimal' => ['0.5'];
        yield 'negative' => ['-0.50'];
        yield 'trailing zero' => ['9999.90'];
    }

    #[DataProvider('provideValidLiterals')]
    public function testItKeepsTheLiteralVerbatim(string $literal): void
    {
        static::assertSame($literal, JsonDecimal::fromString($literal)->literal());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidLiterals(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['abc'];
        yield 'leading zero' => ['01'];
        yield 'exponent' => ['1e5'];
        yield 'comma' => ['1,5'];
        yield 'no decimals after the point' => ['1.'];
        yield 'no integer part' => ['.5'];
        yield 'plus sign' => ['+1'];
        yield 'trailing newline' => ["1\n"];
        yield 'leading space' => [' 1'];
    }

    #[DataProvider('provideInvalidLiterals')]
    public function testItRefusesAnythingButAJsonNumber(string $literal): void
    {
        $exception = DomainAssertions::assertViolationCode('json_decimal.invalid', static fn(): JsonDecimal => JsonDecimal::fromString($literal));

        static::assertSame('The value is not a valid JSON number literal.', $exception->getMessage());
    }
}
