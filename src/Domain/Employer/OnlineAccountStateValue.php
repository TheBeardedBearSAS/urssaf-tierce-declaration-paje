<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

/**
 * Documented values of `etatCompteEnLigne` (api-reference §3.2). The Swagger declares a free string and the exact wire
 * spelling of the last value is not published (§5): see {@see OnlineAccountState} for the raw fallback.
 */
enum OnlineAccountStateValue: string
{
    case Active = 'Actif';
    case Absent = 'Absent';
    case Inactive = 'Inactif';
    case InvalidBankDetails = 'Coordonnées bancaires invalides';
}
