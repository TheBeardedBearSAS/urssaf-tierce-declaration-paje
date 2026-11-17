<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\AddressFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class AddressFactoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return [
            'street_number' => 12,
            'street_letter' => 'B',
            'street_type' => 'R',
            'street_name' => 'DES LILAS',
            'location_complement' => 'RESIDENCE',
            'locality' => 'LE BOURG',
            'municipality_code' => '94022',
            'postal_code' => '94000',
        ];
    }

    public function testFromInput(): void
    {
        $address = (new AddressFactory())->fromInput(self::valid());

        static::assertSame(12, $address->streetNumber());
        static::assertSame('B', $address->streetLetter()?->value());
        static::assertSame('R', $address->streetType()?->value());
        static::assertSame('DES LILAS', $address->streetName());
        static::assertSame('RESIDENCE', $address->locationComplement());
        static::assertSame('LE BOURG', $address->locality());
        static::assertSame('94022', $address->municipalityCode());
        static::assertSame('94000', $address->postalCode()->value());
        static::assertSame([], DomainAssertions::summary((new AddressFactory())->validate(self::valid())));
    }

    public function testOnlyTheMunicipalityAndThePostalCodeAreRequired(): void
    {
        $address = (new AddressFactory())->fromInput(['municipality_code' => '94022', 'postal_code' => '94000', 'street_number' => '7']);

        static::assertSame(7, $address->streetNumber());
        static::assertNull($address->streetName());
        static::assertSame(
            ['error municipality_code municipality_code.required', 'error postal_code postal_code.required'],
            DomainAssertions::summary((new AddressFactory())->validate([])),
        );
    }

    public function testEveryInvalidFieldIsReported(): void
    {
        $result = (new AddressFactory())->validate([
            'street_number' => 0,
            'street_letter' => 'ab',
            'street_type' => 'abcd',
            'street_name' => str_repeat('A', 22),
            'location_complement' => 'abc',
            'locality' => 'abc',
            'municipality_code' => '1',
            'postal_code' => '1',
        ]);

        static::assertSame(
            [
                'error street_letter street_letter.too_long',
                'error street_type street_type.too_long',
                'error postal_code postal_code.too_short',
                'error street_number street_number.out_of_range',
                'error street_name street_name.too_long',
                'error location_complement location_complement.invalid_format',
                'error locality locality.invalid_format',
                'error municipality_code municipality_code.too_short',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testAMissingRequiredFieldDoesNotHideTheOtherViolations(): void
    {
        static::assertSame(
            ['error postal_code postal_code.required', 'error street_name street_name.too_long'],
            DomainAssertions::summary((new AddressFactory())->validate(['municipality_code' => '94022', 'street_name' => str_repeat('A', 22)])),
        );
        static::assertSame(
            ['error municipality_code municipality_code.required', 'error street_number street_number.out_of_range'],
            DomainAssertions::summary((new AddressFactory())->validate(['postal_code' => '94000', 'street_number' => 10_000])),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        foreach (['street_letter' => 'ab', 'street_type' => 'abcd', 'postal_code' => '1', 'street_number' => 0, 'municipality_code' => '1'] as $key => $invalid) {
            $result = (new AddressFactory())->validate([$key => $invalid] + self::valid());
            static::assertCount(1, $result->errors(), $key);
        }
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error street_number street_number.invalid_type', 'error municipality_code municipality_code.required', 'error postal_code postal_code.required'],
            DeclarationSamples::violations(static fn(): mixed => (new AddressFactory())->fromInput(['street_number' => 'x'])),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(AddressFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(AddressFactory::class, 'fromInput', ['input']);
    }
}
