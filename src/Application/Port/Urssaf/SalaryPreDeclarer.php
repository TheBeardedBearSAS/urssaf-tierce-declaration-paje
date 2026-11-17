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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Operations 045 and 046: pre-declares a salary, i.e. validates it and returns the amounts without recording anything
 * (api-reference §2). Safe to retry.
 */
interface SalaryPreDeclarer
{
    /**
     * Pre-declares a childminder salary (045).
     *
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see RejectionKind})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable (transport error, 429, 5xx)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function preDeclareChildminder(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        ChildminderDeclaration $declaration,
    ): PreDeclarationResult;

    /**
     * Pre-declares a home care salary (046).
     *
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see RejectionKind})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable (transport error, 429, 5xx)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function preDeclareHomeCare(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        HomeCareDeclaration $declaration,
    ): PreDeclarationResult;
}
