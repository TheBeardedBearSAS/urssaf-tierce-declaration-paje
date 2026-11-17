<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * `InputSp` (api-reference §2.7): the employee of a declaration, identified by its Pajemploi number or by its NIR,
 * never both (ADR 0004), with the optional birth date. Personal data: nothing of it ends up in an exception.
 */
final class DeclaredEmployeeMapper
{
    /**
     * @return array<string, mixed>
     *
     * @throws InvariantViolation "declared_employee.unsupported" for an identification that is neither by number nor by NIR
     */
    public static function encode(#[SensitiveParameter] DeclaredEmployee $employee): array
    {
        $birthDate = $employee->birthDate();
        $identification = match (true) {
            $employee instanceof EmployeeByNumber => ['numeroPajeSalarie' => $employee->number()->value()],
            $employee instanceof EmployeeByNir => ['nirSalarie' => $employee->nir()->value()],
            default => throw InvariantViolation::because(
                'declared_employee.unsupported',
                'The employee of a declaration is identified by its Pajemploi number or by its NIR.',
            ),
        };

        return OptionalWireValues::present([
            'dtNaissanceSalarie' => $birthDate instanceof CalendarDate ? DateEncoding::encode($birthDate) : null,
        ] + $identification);
    }
}
