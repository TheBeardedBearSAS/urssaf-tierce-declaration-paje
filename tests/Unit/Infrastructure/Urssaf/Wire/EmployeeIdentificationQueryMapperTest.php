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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EmployeeIdentificationQueryMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\QueryEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;

final class EmployeeIdentificationQueryMapperTest extends TestCase
{
    public function testTheNirAndTheBirthDateAreTheOfficialCall(): void
    {
        $query = EmployeeIdentificationQueryMapper::query(
            EmployeeByNir::of(Nir::of('178010701005265'), CalendarDate::fromString('1979-01-01')),
        );

        static::assertSame(['nirSalarie' => '178010701005265', 'dtNaissanceSalarie' => '1979-01-01'], $query);
        static::assertSame('nirSalarie=178010701005265&dtNaissanceSalarie=1979-01-01', QueryEncoding::build($query));
    }

    public function testTheNumberIsSentWithoutBirthDate(): void
    {
        static::assertSame(
            ['numeroPajeSalarie' => 'Y1869005780009'],
            EmployeeIdentificationQueryMapper::query(EmployeeByNumber::of(PajeEmployeeNumber::of('Y1869005780009'))),
        );
    }

    public function testTheNumberIsSentWithItsBirthDate(): void
    {
        static::assertSame(
            ['numeroPajeSalarie' => 'Y1869005780009', 'dtNaissanceSalarie' => '1980-01-30'],
            EmployeeIdentificationQueryMapper::query(
                EmployeeByNumber::of(PajeEmployeeNumber::of('Y1869005780009'), CalendarDate::fromString('1980-01-30')),
            ),
        );
    }

    public function testTheNirIsSentWithoutBirthDate(): void
    {
        static::assertSame(
            ['nirSalarie' => '178010701005265'],
            EmployeeIdentificationQueryMapper::query(EmployeeByNir::of(Nir::of('178010701005265'))),
        );
    }

    public function testAdministrativeDataContributesNoCriterion(): void
    {
        $identity = EmployeeWireSamples::identity();

        static::assertSame([], EmployeeIdentificationQueryMapper::query(EmployeeByAdministrativeData::of($identity)));
        static::assertSame(
            ['dtNaissanceSalarie' => '1980-01-30'],
            EmployeeIdentificationQueryMapper::query(EmployeeByAdministrativeData::of($identity, CalendarDate::fromString('1980-01-30'))),
        );
    }
}
