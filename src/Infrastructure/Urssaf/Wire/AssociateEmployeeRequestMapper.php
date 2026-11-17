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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;

/**
 * 023 `POST /employeurs/{numeroPajeEmployeur}/salarie/associer` (api-reference §2.4): path
 * {@see ApiPaths::associateEmployee()}, query = `dtNaissanceEmployeur` then the identification
 * ({@see EmployeeIdentificationQueryMapper}), body = `InputCreationSalarie`, always present (`{}` for an empty
 * creation, ADR 0002).
 *
 * An {@see \TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData} identification
 * contributes no query criterion (only its optional birth date): its identity is never sent here, the matching data
 * comes from `EmployeeCreation::identity()`, which the Application lot must require to be non-empty in that case
 * (ADR 0009 amendment).
 */
final class AssociateEmployeeRequestMapper
{
    /**
     * @return array<string, string>
     */
    public static function query(#[SensitiveParameter] EmployerReference $employer, #[SensitiveParameter] EmployeeIdentification $identification): array
    {
        return EmployerQueryMapper::query($employer) + EmployeeIdentificationQueryMapper::query($identification);
    }

    public static function body(EmployeeCreation $creation): JsonObject
    {
        return EmployeeCreationMapper::encode($creation);
    }
}
