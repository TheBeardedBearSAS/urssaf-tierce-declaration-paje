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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Civility;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AdministrativeIdentityMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class AdministrativeIdentityMapperTest extends TestCase
{
    public function testTheFullIdentityIsEncodedAsTheOfficialExample(): void
    {
        $expected = Fixtures::section('associate_employee_request_valid', 'inputIdentificationSalarie');
        $members = AdministrativeIdentityMapper::encode(EmployeeWireSamples::identity())->members();

        static::assertSame($expected, $members);
        static::assertSame(array_keys($expected), array_keys($members));
    }

    public function testAnEmptyIdentityIsAnEmptyJsonObject(): void
    {
        $empty = AdministrativeIdentity::of(null, null, null, null, null, null, null, null);

        static::assertSame([], AdministrativeIdentityMapper::encode($empty)->members());
        static::assertSame('{}', JsonEncoder::encode(AdministrativeIdentityMapper::encode($empty)));
    }

    public function testOnlyTheFieldsThatAreSetAreSent(): void
    {
        $identity = AdministrativeIdentity::of(
            Civility::madam(),
            null,
            UpperCaseName::of('DURAND', 'usage_name'),
            null,
            null,
            null,
            '07',
            null,
        );

        static::assertSame(
            ['cdCivilite' => '2', 'departementNaissance' => '07', 'nomUsage' => 'DURAND'],
            AdministrativeIdentityMapper::encode($identity)->members(),
        );
    }
}
