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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Address;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * `InputCreationSalarie`, the body of 023 (api-reference §2.4). Keys in the (alphabetical) order of the official
 * example, only the fields that are set. Always a {@see JsonObject}: an empty creation is `{}` (ADR 0002, api-reference
 * §4 decision 9).
 *
 * `inputIdentificationSalarie` comes from {@see EmployeeCreation::identity()} only (never from the identification of
 * the query). An identity that is present but sets no field is sent as `{}`: the caller's input is not reinterpreted.
 */
final class EmployeeCreationMapper
{
    public static function encode(#[SensitiveParameter] EmployeeCreation $creation): JsonObject
    {
        $licenceStartDate = $creation->licenceStartDate();
        $birthDate = $creation->birthDate();
        $address = $creation->address();
        $identity = $creation->identity();

        return JsonObject::of(array_filter([
            'dtDebutAgrement' => $licenceStartDate instanceof CalendarDate ? DateEncoding::encode($licenceStartDate) : null,
            'dtNaissanceSalarie' => $birthDate instanceof CalendarDate ? DateEncoding::encode($birthDate) : null,
            'inputAdresseSalarie' => $address instanceof Address ? AddressMapper::encode($address) : null,
            'inputIdentificationSalarie' => $identity instanceof AdministrativeIdentity ? AdministrativeIdentityMapper::encode($identity) : null,
            'nirSalarie' => $creation->nir()?->value(),
            'typeActiviteSalarie' => $creation->activityType()?->value(),
        ], static fn(array|string|JsonObject|null $value): bool => $value !== null));
    }
}
