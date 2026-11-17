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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentificationKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclaredEmployeeMapper;

final class DeclaredEmployeeMapperTest extends TestCase
{
    public function testAnEmployeeByNumberSendsOnlyTheNumberAndTheBirthDate(): void
    {
        static::assertSame(
            ['dtNaissanceSalarie' => '1980-01-30', 'numeroPajeSalarie' => 'Y2926822480004'],
            DeclaredEmployeeMapper::encode(DeclarationWireSamples::employee()),
        );
    }

    public function testAnEmployeeByNirSendsOnlyTheNirAndTheBirthDate(): void
    {
        static::assertSame(
            ['dtNaissanceSalarie' => '1980-01-30', 'nirSalarie' => '123456789012345'],
            DeclaredEmployeeMapper::encode(
                EmployeeByNir::of(Nir::of('123456789012345'), CalendarDate::fromString('1980-01-30')),
            ),
        );
    }

    public function testTheBirthDateIsOmittedWhenNotSet(): void
    {
        static::assertSame(
            ['numeroPajeSalarie' => 'Y2926822480004'],
            DeclaredEmployeeMapper::encode(EmployeeByNumber::of(PajeEmployeeNumber::of('Y2926822480004'))),
        );
        static::assertSame(
            ['nirSalarie' => '123456789012345'],
            DeclaredEmployeeMapper::encode(EmployeeByNir::of(Nir::of('123456789012345'))),
        );
    }

    public function testAnotherIdentificationIsRefusedWithoutPersonalData(): void
    {
        $other = new class implements DeclaredEmployee {
            public function kind(): EmployeeIdentificationKind
            {
                return EmployeeIdentificationKind::AdministrativeData;
            }

            public function birthDate(): ?CalendarDate
            {
                return null;
            }
        };

        try {
            DeclaredEmployeeMapper::encode($other);
            static::fail('Expected an InvariantViolation.');
        } catch (InvariantViolation $violation) {
            static::assertSame('declared_employee.unsupported', $violation->violationCode());
            static::assertSame(
                'The employee of a declaration is identified by its Pajemploi number or by its NIR.',
                $violation->getMessage(),
            );
        }
    }
}
