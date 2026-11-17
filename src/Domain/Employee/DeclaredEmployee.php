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
 * Identification usable in a declaration `InputSp` (ADR 0004): the contract has no administrative data there, so only
 * {@see EmployeeByNumber} and {@see EmployeeByNir} implement it.
 */
interface DeclaredEmployee extends EmployeeIdentification {}
