<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

/**
 * Identification usable in a declaration `InputSp` (ADR 0004): the contract has no administrative data there, so only
 * {@see EmployeeByNumber} and {@see EmployeeByNir} implement it.
 */
interface DeclaredEmployee extends EmployeeIdentification {}
