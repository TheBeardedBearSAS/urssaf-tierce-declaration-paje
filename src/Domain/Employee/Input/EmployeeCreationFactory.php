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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw creation data of operation 023 into an {@see EmployeeCreation}, reporting every violation
 * (notification pattern, ADR 0000 item 4). Every field is optional (ADR 0009); an input without any field gives an
 * empty creation (`{}`). The "non-empty identity" rule of an association by administrative data belongs to the
 * association use case (ADR 0009 amendment), not to this factory.
 *
 * Input keys: "activity_type", "birth_date" (Y-m-d), "nir", "licence_start_date" (Y-m-d), "address" (see
 * {@see AddressFactory}), "identity" (see {@see AdministrativeIdentityFactory}).
 */
final readonly class EmployeeCreationFactory
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
    public function fromInput(#[SensitiveParameter] array $input): EmployeeCreation
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    private function parse(RawFields $fields): ?EmployeeCreation
    {
        $errorsBefore = $fields->errorCount();
        $activityType = $fields->optional('activity_type', ActivityType::of(...));
        $birthDate = $fields->optional('birth_date', CalendarDate::fromString(...));
        $nir = $fields->optional('nir', Nir::of(...));
        $licenceStartDate = $fields->optional('licence_start_date', CalendarDate::fromString(...));
        $address = $fields->optionalNested('address', (new AddressFactory())->parse(...));
        $identity = $fields->optionalNested('identity', (new AdministrativeIdentityFactory())->parse(...));

        return $fields->errorCount() > $errorsBefore
            ? null
            : EmployeeCreation::of($activityType, $birthDate, $nir, $licenceStartDate, $address, $identity);
    }
}
