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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EmployeeCreationMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class EmployeeCreationMapperTest extends TestCase
{
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function testTheFullCreationIsEncodedAsTheOfficialExampleKeyOrderIncluded(): void
    {
        // numeroPajeSalarie is a query parameter of 023: the official file is a flattened Swagger example.
        $official = Fixtures::json('associate_employee_request_valid');
        unset($official['numeroPajeSalarie']);

        static::assertSame(
            json_encode($official, self::FLAGS),
            JsonEncoder::encode(EmployeeCreationMapper::encode(EmployeeWireSamples::creation())),
        );
    }

    public function testAnEmptyCreationIsAnEmptyJsonObject(): void
    {
        $empty = EmployeeCreation::of(null, null, null, null, null, null);

        static::assertSame('{}', JsonEncoder::encode(EmployeeCreationMapper::encode($empty)));
    }

    public function testAnEmptyIdentityIsSentAsAnEmptyObject(): void
    {
        $creation = EmployeeCreation::of(null, null, null, null, null, AdministrativeIdentity::of(null, null, null, null, null, null, null, null));

        static::assertSame(
            '{"inputIdentificationSalarie":{}}',
            JsonEncoder::encode(EmployeeCreationMapper::encode($creation)),
        );
    }

    public function testEachFieldIsSentOnItsOwn(): void
    {
        $date = CalendarDate::fromString('1980-01-30');

        static::assertSame(
            ['dtDebutAgrement' => '1980-01-30'],
            EmployeeCreationMapper::encode(EmployeeCreation::of(null, null, null, $date, null, null))->members(),
        );
        static::assertSame(
            ['dtNaissanceSalarie' => '1980-01-30'],
            EmployeeCreationMapper::encode(EmployeeCreation::of(null, $date, null, null, null, null))->members(),
        );
        static::assertSame(
            ['nirSalarie' => '178010701005265', 'typeActiviteSalarie' => 'G'],
            EmployeeCreationMapper::encode(EmployeeCreation::of(ActivityType::homeCare(), null, Nir::of('178010701005265'), null, null, null))->members(),
        );
        static::assertSame(
            ['inputAdresseSalarie' => ['codeCommune' => '94022', 'codePostal' => '94600', 'complementLocalisation' => 'RESIDENCE LES LILAS', 'lettreVoie' => 'B', 'libelleVoie' => 'DE LA PAIX', 'lieuDit' => 'LE BOURG', 'numVoie' => 12, 'typeVoie' => 'R']],
            EmployeeCreationMapper::encode(EmployeeCreation::of(null, null, null, null, EmployeeWireSamples::address(), null))->members(),
        );
    }
}
