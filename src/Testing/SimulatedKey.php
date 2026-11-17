<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Testing;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;

/**
 * Opaque keys under which the simulator recognises an employee or a child: hashes, so that no personal data is kept
 * as such (ADR 0007).
 *
 * @internal
 */
final class SimulatedKey
{
    /**
     * The key of an employee identification: the number, the NIR or the administrative data (names, birth date and
     * birth place).
     */
    public static function employee(EmployeeIdentification $employee): string
    {
        return match (true) {
            $employee instanceof EmployeeByNumber => self::hash('number', $employee->number()->value()),
            $employee instanceof EmployeeByNir => self::hash('nir', $employee->nir()->value()),
            $employee instanceof EmployeeByAdministrativeData => self::administrativeData($employee),
            default => self::hash('kind', $employee->kind()->value),
        };
    }

    public static function employeeNumber(string $number): string
    {
        return self::hash('number', $number);
    }

    public static function child(Child $child): string
    {
        return self::hash(
            'child',
            $child->birthDate()->toString(),
            $child->lastName()->value(),
            $child->firstName()->value(),
        );
    }

    private static function administrativeData(EmployeeByAdministrativeData $employee): string
    {
        $identity = $employee->identity();

        return self::hash(
            'identity',
            $identity->birthName()?->value() ?? '',
            $identity->usageName()?->value() ?? '',
            $identity->firstName()?->value() ?? '',
            $identity->birthDate()?->toString() ?? '',
            $identity->birthCity() ?? '',
            $identity->birthCountryCode() ?? '',
        );
    }

    /**
     * @param string ...$parts the kind of key, then its values
     */
    private static function hash(string ...$parts): string
    {
        return hash('sha256', serialize($parts));
    }
}
