<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\StreetType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class StreetTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDocumentedTypes(): iterable
    {
        foreach (['ALL', 'AV', 'BAT', 'BD', 'CAN', 'CHS', 'CHE', 'CI', 'CL', 'C', 'COG', 'FG', 'HAM', 'IMB', 'IMP', 'LOT', 'MAI', 'MTE', 'PAS', 'PAR', 'PAV', 'PL', 'POR', 'Q', 'QU', 'RAM', 'RES', 'RTE', 'R', 'RUL', 'SEN', 'SQ', 'VO'] as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('provideDocumentedTypes')]
    public function testStreetTypeRecognisesTheDocumentedCodes(string $code): void
    {
        $type = StreetType::of($code);

        static::assertSame($code, $type->value());
        static::assertTrue($type->isDocumented());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUndocumentedTypes(): iterable
    {
        yield 'XYZ' => ['XYZ'];
        yield 'one letter' => ['X'];
        yield 'hyphen' => ['-'];
        yield 'apostrophe and space' => ["' "];
    }

    #[DataProvider('provideUndocumentedTypes')]
    public function testStreetTypeAcceptsAnyValueOfThePattern(string $value): void
    {
        $type = StreetType::of($value);

        static::assertSame($value, $type->value());
        static::assertFalse($type->isDocumented());
    }

    public function testStreetTypeDocumentedLookupIsCaseSensitiveAndStrict(): void
    {
        static::assertFalse(StreetType::of('A')->isDocumented());
        static::assertFalse(StreetType::of('AVE')->isDocumented());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidTypes(): iterable
    {
        yield 'empty' => ['', 'street_type.too_short'];
        yield 'four characters' => ['ABCD', 'street_type.too_long'];
        yield 'lower case' => ['av', 'street_type.invalid_format'];
        yield 'digit' => ['A1', 'street_type.invalid_format'];
        yield 'accent' => ['ÉA', 'street_type.invalid_format'];
        yield 'invalid at the end' => ['AV1', 'street_type.invalid_format'];
        yield 'invalid at the start' => ['1AV', 'street_type.invalid_format'];
    }

    #[DataProvider('provideInvalidTypes')]
    public function testStreetTypeRejectsInvalidValues(string $value, string $code): void
    {
        static::assertSame(
            $code,
            DomainAssertions::assertViolationCode($code, static fn(): StreetType => StreetType::of($value))->violationCode(),
        );
    }

    public function testStreetTypeEquality(): void
    {
        static::assertTrue(StreetType::of('AV')->equals(StreetType::of('AV')));
        static::assertFalse(StreetType::of('AV')->equals(StreetType::of('BD')));
    }
}
