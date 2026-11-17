<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountStateValue;

final class OnlineAccountStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, OnlineAccountStateValue}>
     */
    public static function provideDocumentedValues(): iterable
    {
        yield 'active' => ['Actif', OnlineAccountStateValue::Active];
        yield 'absent' => ['Absent', OnlineAccountStateValue::Absent];
        yield 'inactive' => ['Inactif', OnlineAccountStateValue::Inactive];
        yield 'invalid bank details' => ['Coordonnées bancaires invalides', OnlineAccountStateValue::InvalidBankDetails];
        yield 'surrounding whitespace' => [" Actif\n", OnlineAccountStateValue::Active];
    }

    #[DataProvider('provideDocumentedValues')]
    public function testDocumentedMapsTheWireValue(string $raw, OnlineAccountStateValue $expected): void
    {
        static::assertSame($expected, OnlineAccountState::of($raw)->documented());
    }

    public function testAnUnlistedValueIsKeptRaw(): void
    {
        $state = OnlineAccountState::of('Bloqué');

        static::assertNull($state->documented());
        static::assertSame('Bloqué', $state->raw());
    }

    public function testRawIsKeptVerbatim(): void
    {
        static::assertSame(' Actif ', OnlineAccountState::of(' Actif ')->raw());
    }

    public function testEnumValuesAreTheWireSpellings(): void
    {
        static::assertSame(['Actif', 'Absent', 'Inactif', 'Coordonnées bancaires invalides'], array_column(OnlineAccountStateValue::cases(), 'value'));
    }

    public function testEquality(): void
    {
        static::assertTrue(OnlineAccountState::of('Actif')->equals(OnlineAccountState::of('Actif')));
        static::assertFalse(OnlineAccountState::of('Actif')->equals(OnlineAccountState::of('Absent')));
    }
}
