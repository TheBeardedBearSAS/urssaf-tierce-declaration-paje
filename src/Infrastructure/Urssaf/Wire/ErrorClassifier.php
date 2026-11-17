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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * Translates URSSAF error codes into {@see RejectionKind}, so that the application never compares wire strings
 * (ADR 0000, ADR 0005). A code outside the catalogue degrades to {@see RejectionKind::Unknown}, never to a wrong
 * meaning.
 *
 * Several errors may be returned together: the kind with the highest priority wins, from the most specific and
 * actionable to the most generic (see {@see self::PRIORITY}). An error of the catalogue that has no dedicated kind is
 * a {@see RejectionKind::BusinessRule}, `ERREUR_TECHNIQUE` (HTTP 400, hourly rate of 100 EUR or more) included: it is a
 * deterministic rejection of the input, never retried (ADR 0005 amendment). An empty list or only unknown codes is {@see RejectionKind::Unknown}.
 */
final class ErrorClassifier
{
    /**
     * Highest priority first. A duplicate comes first because it settles the declaration as already recorded
     * (ADR 0003); the declaration window and the missing association are fixable by the host; the identification
     * problems of the employer (the birth date it references included) follow, then the birth date of the employee, then the mandate, the malformed request and the technical errors; the other
     * business rules come last before unknown.
     *
     * @var list<RejectionKind>
     */
    private const PRIORITY = [
        RejectionKind::DuplicateDeclaration,
        RejectionKind::DeclarationWindow,
        RejectionKind::EmployeeNotAssociated,
        RejectionKind::EmployerNotFound,
        RejectionKind::BirthDateMismatch,
        RejectionKind::EmployeeBirthDateMismatch,
        RejectionKind::MandateMissing,
        RejectionKind::MandateHeldElsewhere,
        RejectionKind::InvalidParameter,
        RejectionKind::Technical,
        RejectionKind::BusinessRule,
    ];

    public static function classify(ApiErrors $errors): RejectionKind
    {
        $kinds = array_map(self::kindOf(...), $errors->all());

        foreach (self::PRIORITY as $kind) {
            if (\in_array($kind, $kinds, true)) {
                return $kind;
            }
        }

        return RejectionKind::Unknown;
    }

    private static function kindOf(ApiError $error): RejectionKind
    {
        $code = ApiErrorCode::fromCode($error->code());

        return match ($code) {
            null => RejectionKind::Unknown,
            ApiErrorCode::ChildminderDeclarationExists, ApiErrorCode::HomeCareDeclarationExists => RejectionKind::DuplicateDeclaration,
            ApiErrorCode::DeclarationTooEarly,
            ApiErrorCode::DeclarationNoLongerAllowed,
            ApiErrorCode::EmploymentPeriodInvalid,
            ApiErrorCode::EmploymentPeriodTooOld => RejectionKind::DeclarationWindow,
            ApiErrorCode::EmployeeNotAssociated => RejectionKind::EmployeeNotAssociated,
            ApiErrorCode::EmployerNotFound, ApiErrorCode::EmployerMissing => RejectionKind::EmployerNotFound,
            ApiErrorCode::BirthDateMismatch => RejectionKind::BirthDateMismatch,
            ApiErrorCode::EmployeeBirthDateMismatch => RejectionKind::EmployeeBirthDateMismatch,
            ApiErrorCode::MandateMissing => RejectionKind::MandateMissing,
            ApiErrorCode::MandateHeldElsewhere => RejectionKind::MandateHeldElsewhere,
            ApiErrorCode::InvalidParameter => RejectionKind::InvalidParameter,
            ApiErrorCode::TechnicalError => RejectionKind::Technical,
            default => RejectionKind::BusinessRule,
        };
    }
}
