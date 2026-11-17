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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;

/**
 * 021 `POST /salaries/verifier` (api-reference §2.2): path {@see ApiPaths::verifyEmployee()}, the identification in
 * the query ({@see EmployeeIdentificationQueryMapper}) and a body that is always present (ADR 0002): the
 * `InputIdentificationSalarie` of an identification by administrative data, `{}` otherwise.
 */
final class VerifyEmployeeRequestMapper
{
    /**
     * @return array<string, string>
     */
    public static function query(#[SensitiveParameter] EmployeeIdentification $identification): array
    {
        return EmployeeIdentificationQueryMapper::query($identification);
    }

    public static function body(EmployeeIdentification $identification): JsonObject
    {
        return $identification instanceof EmployeeByAdministrativeData
            ? AdministrativeIdentityMapper::encode($identification->identity())
            : JsonObject::of([]);
    }
}
