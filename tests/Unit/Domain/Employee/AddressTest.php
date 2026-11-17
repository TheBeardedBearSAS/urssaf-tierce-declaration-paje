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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Address;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\StreetType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class AddressTest extends TestCase
{
    public function testOfKeepsEveryField(): void
    {
        $letter = StreetLetter::of('B');
        $type = StreetType::of('R');
        $postalCode = PostalCode::fromString('94600');

        $address = Address::of(12, $letter, $type, 'DE LA PAIX', 'BATIMENT A', "LE BOIS L'ABBE", '94022', $postalCode);

        static::assertSame(12, $address->streetNumber());
        static::assertSame($letter, $address->streetLetter());
        static::assertSame($type, $address->streetType());
        static::assertSame('DE LA PAIX', $address->streetName());
        static::assertSame('BATIMENT A', $address->locationComplement());
        static::assertSame("LE BOIS L'ABBE", $address->locality());
        static::assertSame('94022', $address->municipalityCode());
        static::assertSame($postalCode, $address->postalCode());
    }

    public function testOnlyTheRequiredFieldsAreNeeded(): void
    {
        $address = Address::of(null, null, null, null, null, null, '94022', PostalCode::fromString('94600'));

        static::assertNull($address->streetNumber());
        static::assertNull($address->streetLetter());
        static::assertNull($address->streetType());
        static::assertNull($address->streetName());
        static::assertNull($address->locationComplement());
        static::assertNull($address->locality());
    }

    public function testEmptyTextsAreTreatedAsAbsent(): void
    {
        $address = Address::of(null, null, null, '', '', '', '94022', PostalCode::fromString('94600'));

        static::assertNull($address->streetName());
        static::assertNull($address->locationComplement());
        static::assertNull($address->locality());
    }

    public function testBoundariesAreAccepted(): void
    {
        $address = Address::of(
            9999,
            null,
            null,
            str_repeat('é', 21),
            str_repeat('A', 32),
            str_repeat('B', 32),
            'ééééé',
            PostalCode::fromString('94600'),
        );

        static::assertSame(9999, $address->streetNumber());
        static::assertSame(str_repeat('é', 21), $address->streetName());
        static::assertSame(1, Address::of(1, null, null, null, null, null, '94022', PostalCode::fromString('94600'))->streetNumber());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function provideInvalidInputs(): iterable
    {
        yield 'street number zero' => [['number' => 0], ['error streetNumber street_number.out_of_range']];
        yield 'street number negative' => [['number' => -1], ['error streetNumber street_number.out_of_range']];
        yield 'street number too large' => [['number' => 10_000], ['error streetNumber street_number.out_of_range']];
        yield 'street name too long' => [['name' => str_repeat('A', 22)], ['error streetName street_name.too_long']];
        yield 'complement too long' => [['complement' => str_repeat('A', 33)], ['error locationComplement location_complement.too_long']];
        yield 'complement lower case' => [['complement' => 'bat a'], ['error locationComplement location_complement.invalid_format']];
        yield 'complement accent' => [['complement' => 'BÂT'], ['error locationComplement location_complement.invalid_format']];
        yield 'complement digit at the end' => [['complement' => 'BAT 1'], ['error locationComplement location_complement.invalid_format']];
        yield 'complement digit at the start' => [['complement' => '1 BAT'], ['error locationComplement location_complement.invalid_format']];
        yield 'locality too long' => [['locality' => str_repeat('A', 33)], ['error locality locality.too_long']];
        yield 'locality digit' => [['locality' => 'LE 3'], ['error locality locality.invalid_format']];
        yield 'municipality too short' => [['municipality' => '9402'], ['error municipalityCode municipality_code.too_short']];
        yield 'municipality empty' => [['municipality' => ''], ['error municipalityCode municipality_code.too_short']];
        yield 'municipality too long' => [['municipality' => '940222'], ['error municipalityCode municipality_code.too_long']];
        yield 'every field at once' => [
            ['number' => 0, 'name' => str_repeat('A', 22), 'complement' => 'x', 'locality' => 'y', 'municipality' => '1'],
            [
                'error streetNumber street_number.out_of_range',
                'error streetName street_name.too_long',
                'error locationComplement location_complement.invalid_format',
                'error locality locality.invalid_format',
                'error municipalityCode municipality_code.too_short',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @param list<string>         $expected
     */
    #[DataProvider('provideInvalidInputs')]
    public function testOfReportsEveryViolation(array $overrides, array $expected): void
    {
        /** @var int|null $number */
        $number = $overrides['number'] ?? 1;
        /** @var string|null $name */
        $name = $overrides['name'] ?? null;
        /** @var string|null $complement */
        $complement = $overrides['complement'] ?? null;
        /** @var string|null $locality */
        $locality = $overrides['locality'] ?? null;
        /** @var string $municipality */
        $municipality = $overrides['municipality'] ?? '94022';

        try {
            Address::of($number, null, null, $name, $complement, $locality, $municipality, PostalCode::fromString('94600'));
            static::fail('Expected a ValidationFailed.');
        } catch (ValidationFailed $failed) {
            static::assertSame($expected, DomainAssertions::summary($failed->result()));
        }
    }

    public function testViolationMessagesNeverEchoTheValues(): void
    {
        try {
            Address::of(null, null, null, null, 'secret value', null, '94022', PostalCode::fromString('94600'));
            static::fail('Expected a ValidationFailed.');
        } catch (ValidationFailed $failed) {
            foreach ($failed->result()->all() as $violation) {
                static::assertStringNotContainsString('secret', $violation->message());
            }
        }
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $dump = print_r(Address::of(12, null, null, 'RUE SECRETE', null, null, '94022', PostalCode::fromString('94600')), true);

        static::assertStringNotContainsString('SECRETE', $dump);
        static::assertStringNotContainsString('94022', $dump);
        static::assertStringContainsString('***', $dump);
    }
}
