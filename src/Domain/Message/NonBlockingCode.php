<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message;

/**
 * The 14 non-blocking codes of the specification (api-reference §3.6, PDF §4.4.2), returned in `erreursNonBloquantes`
 * with HTTP 200. Typed view of {@see NonBlockingMessage::knownCode()}: the raw code is always kept.
 */
enum NonBlockingCode: string
{
    case EmployerNotAuthorised = 'ER_API_020_NB_0000';
    case EmployerBankDetailsInvalid = 'ER_API_020_NB_0010';
    case EmployeeNotVerified = 'ER_API_021_NB_0000';
    case ChildUnknown = 'ER_API_022_NB_0000';
    case ChildNoLongerEligible = 'ER_API_041_NB_0010';
    case CalculationModeInconsistent = 'ER_API_041_NB_0020';
    case EmployeeAlreadyAssociated = 'ER_API_023_NB_0000';
    case AgreementYearInconsistentWithBirth = 'ER_API_023_DATE_AGREMENT_NB_0000';
    case AgreementEndDateNotInFuture = 'ER_API_023_DATE_AGREMENT_NB_0010';
    case AgreementDateIncorrect = 'ER_AG_NB_00000';
    case AgreementExpiring = 'ER_AG_NB_00010';
    case ChildminderHourlyRateCapped = 'ER_API_050_NB_0016';
    case HomeCareHourlyRateCapped = 'ER_API_051_NB_0016';
    case SalaryGapWithPreviousDeclarations = 'ER_API_DECLA_NB_0010';
}
