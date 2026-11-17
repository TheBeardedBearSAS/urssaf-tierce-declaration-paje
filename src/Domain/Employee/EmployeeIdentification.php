<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Identification criterion of an employee for operations 021 and 023 (ADR 0004, api-reference §4 decision 10). Sum
 * type: {@see EmployeeByNumber}, {@see EmployeeByNir} and {@see EmployeeByAdministrativeData}. Personal data.
 */
interface EmployeeIdentification
{
    public function kind(): EmployeeIdentificationKind;

    /**
     * Optional `dtNaissanceSalarie` of the query.
     */
    public function birthDate(): ?CalendarDate;
}
