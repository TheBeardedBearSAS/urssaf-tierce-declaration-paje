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

/**
 * How an employee is identified (ADR 0004): by Pajemploi number (priority 1), by NIR (2) or by administrative data (3).
 * Stored instead of the NIR or the identity (ADR 0007).
 */
enum EmployeeIdentificationKind: string
{
    case Number = 'number';
    case Nir = 'nir';
    case AdministrativeData = 'administrative_data';
}
