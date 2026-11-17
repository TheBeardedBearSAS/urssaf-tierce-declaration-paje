<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw identification criteria of an employee (operations 021 and 023) into an {@see EmployeeIdentification},
 * reporting every violation (notification pattern, ADR 0000 item 4).
 *
 * The criteria have a priority (api-reference §2.2, ADR 0004): the Pajemploi number, then the NIR, then the
 * administrative data. The highest-priority criterion given is kept, as URSSAF does (the lower ones are never
 * refused locally, ADR 0009), but every given criterion is validated. Without any criterion, or with an identity that
 * sets no field, "employee_identification.empty" is reported (api-reference §4 decision 10).
 *
 * Input keys: "employee_number", "nir", "birth_date" (Y-m-d, optional, `dtNaissanceSalarie` of the query), "identity"
 * (see {@see AdministrativeIdentityFactory}).
 */
final readonly class EmployeeIdentificationFactory
{
    /**
     * @param array<array-key, mixed> $input
     */
    public function validate(#[SensitiveParameter] array $input): ValidationResult
    {
        return RawFields::validate($input, $this->parse(...));
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function fromInput(#[SensitiveParameter] array $input): EmployeeIdentification
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    private function parse(RawFields $fields): ?EmployeeIdentification
    {
        $errorsBefore = $fields->errorCount();
        $identity = $fields->optionalNested('identity', (new AdministrativeIdentityFactory())->parse(...));
        $identityReported = $fields->errorCount() > $errorsBefore;
        $birthDate = $fields->optional('birth_date', CalendarDate::fromString(...));
        $criteriaErrorsBefore = $fields->errorCount();
        $declared = (new DeclaredEmployeeFactory())->parseCriteria($fields, $birthDate);
        if ($declared instanceof DeclaredEmployee || $fields->errorCount() > $criteriaErrorsBefore) {
            return $fields->errorCount() > $errorsBefore ? null : $declared;
        }
        if ($identity instanceof AdministrativeIdentity) {
            $employee = $fields->build('identity', static fn(): EmployeeByAdministrativeData => EmployeeByAdministrativeData::of($identity, $birthDate));

            return $fields->errorCount() > $errorsBefore ? null : $employee;
        }
        if (!$identityReported) {
            $fields->add(InvalidValue::empty('employee_identification')->toViolation('identity'));
        }

        return null;
    }
}
