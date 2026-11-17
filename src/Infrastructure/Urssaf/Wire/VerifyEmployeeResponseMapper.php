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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * 021 response `OutputVerificationSp` (api-reference §2.2): decoded by {@see EmployeeAccountMapper}, whose docblock
 * gives the per-field decision for an absent field.
 */
final class VerifyEmployeeResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse when the body is not a JSON object (`{}` is accepted) or a field is malformed
     */
    public static function map(Payload $payload): EmployeeAccount
    {
        $payload->requireJsonObject();

        return EmployeeAccountMapper::decode($payload);
    }
}
