<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf;

/**
 * What a rejection (HTTP 400) means for the application, decided from the URSSAF error codes (ADR 0005). The wire
 * codes never leave the infrastructure layer.
 */
enum RejectionKind: string
{
    case InvalidParameter = 'invalid_parameter';
    case EmployerNotFound = 'employer_not_found';
    case BirthDateMismatch = 'birth_date_mismatch';
    case EmployeeBirthDateMismatch = 'employee_birth_date_mismatch';
    case MandateMissing = 'mandate_missing';
    case MandateHeldElsewhere = 'mandate_held_elsewhere';
    case DuplicateDeclaration = 'duplicate_declaration';
    case DeclarationWindow = 'declaration_window';
    case EmployeeNotAssociated = 'employee_not_associated';
    case BusinessRule = 'business_rule';
    case Technical = 'technical';
    case Unknown = 'unknown';
}
