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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Address;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AddressMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class AddressMapperTest extends TestCase
{
    public function testTheFullAddressIsEncodedAsTheOfficialExample(): void
    {
        $expected = Fixtures::section('associate_employee_request_valid', 'inputAdresseSalarie');
        $encoded = AddressMapper::encode(EmployeeWireSamples::address());

        static::assertSame($expected, $encoded);
        static::assertSame(array_keys($expected), array_keys($encoded));
    }

    public function testOnlyTheRequiredFieldsAreSentWhenTheOthersAreAbsent(): void
    {
        $address = Address::of(null, null, null, null, null, null, '94022', PostalCode::fromString('94600'));

        static::assertSame(['codeCommune' => '94022', 'codePostal' => '94600'], AddressMapper::encode($address));
    }

    public function testEachOptionalFieldIsSentOnItsOwn(): void
    {
        $postal = PostalCode::fromString('94600');

        static::assertSame(
            ['codeCommune' => '94022', 'codePostal' => '94600', 'numVoie' => 1],
            AddressMapper::encode(Address::of(1, null, null, null, null, null, '94022', $postal)),
        );
        static::assertSame(
            ['codeCommune' => '94022', 'codePostal' => '94600', 'lieuDit' => 'LE BOURG'],
            AddressMapper::encode(Address::of(null, null, null, null, null, 'LE BOURG', '94022', $postal)),
        );
    }
}
