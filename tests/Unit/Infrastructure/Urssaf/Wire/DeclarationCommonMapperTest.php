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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationCommonMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;

final class DeclarationCommonMapperTest extends TestCase
{
    public function testTheOfficialExampleIsEncodedInItsKeyOrder(): void
    {
        foreach (['declare_ama_request_valid', 'declare_ged_request_valid', 'predeclare_ama_request_valid'] as $fixture) {
            WireBodyAssertions::assertBodyIsFixtureSection($fixture, 'inputDeclCommun', DeclarationCommonMapper::encode(DeclarationWireSamples::common()));
        }
    }

    public function testEveryValueIsEncodedWithItsWireName(): void
    {
        $common = DeclarationCommon::of(
            DeclarationPeriod::of(
                CalendarDate::fromString('2026-10-01'),
                CalendarDate::fromString('2026-10-31'),
                CalendarDate::fromString('2026-11-05'),
            ),
            151,
            HalfDays::fromHalfDays(5),
            DeclarationWireSamples::amount('11.02'),
            12,
            DeclarationWireSamples::amount('50'),
            DeclarationWireSamples::amount('1234.5'),
            DeclarationWireSamples::amount('9999.99'),
        );

        static::assertSame(
            '{"dtDebutPeriode":"2026-10-01","dtFinPeriode":"2026-10-31","dtPaiementSalaire":"2026-11-05",'
            . '"mntAcompteSalarie":50.00,"mntIndemnitesKilometriques":9999.99,"mntSalaireHoraireNet":11.02,'
            . '"mntSalaireNetMensuel":1234.50,"nbHeures":151,"nbHeuresSpecifiques":12,"nbJoursCongesPayes":2.5}',
            JsonEncoder::encode(DeclarationCommonMapper::encode($common)),
        );
    }

    public function testOptionalValuesThatAreNotSetAreOmitted(): void
    {
        $common = DeclarationCommon::of(
            DeclarationPeriod::of(
                CalendarDate::fromString('2026-10-01'),
                CalendarDate::fromString('2026-10-31'),
                CalendarDate::fromString('2026-11-05'),
            ),
            1,
            null,
            null,
            null,
            null,
            DeclarationWireSamples::amount('0.01'),
            null,
        );

        static::assertSame(
            '{"dtDebutPeriode":"2026-10-01","dtFinPeriode":"2026-10-31","dtPaiementSalaire":"2026-11-05",'
            . '"mntSalaireNetMensuel":0.01,"nbHeures":1}',
            JsonEncoder::encode(DeclarationCommonMapper::encode($common)),
        );
    }
}
