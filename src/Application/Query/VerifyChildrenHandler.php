<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ChildCheckView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ChildrenVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;

/**
 * Method 022 as a query: nothing is stored, every failure propagates (a read is safe to retry). One check per submitted
 * child, in the order of the answer, without the child identity (ADR 0007).
 */
final readonly class VerifyChildrenHandler
{
    public function __construct(private ChildrenVerifier $verifier) {}

    /**
     * @return list<ChildCheckView>
     *
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(VerifyChildren $query): array
    {
        return ChildCheckView::fromChecks($this->verifier->verifyChildren($query->tenantId, $query->employer, $query->children));
    }
}
