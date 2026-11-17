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

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Operation 021: verifies an employee account (api-reference §2). A read, safe to retry. The identification (NIR, birth data) is
 * personal data: it travels in the body, never in a log (ADR 0007).
 */
interface EmployeeVerifier
{
    /**
     * Returns the account of the identified employee.
     *
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see RejectionKind})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable (transport error, 429, 5xx)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function verifyEmployee(TenantId $tenantId, #[SensitiveParameter] EmployeeIdentification $employee): EmployeeAccount;
}
