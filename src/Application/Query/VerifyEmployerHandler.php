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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerStatusView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Method 020 as a query: every failure propagates (a read is safe to retry). The only write is the refresh of the
 * snapshot of the tracked account of the same employer (same number and birth date), after a successful call.
 */
final readonly class VerifyEmployerHandler
{
    public function __construct(
        private EmployerVerifier $verifier,
        private EmployerAccountRepository $accounts,
        private EmployerStatusRefresher $refresher,
        private Clock $clock,
    ) {}

    /**
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(VerifyEmployer $query): EmployerStatusView
    {
        $verification = $this->verifier->verifyEmployer($query->tenantId, $query->employer);
        $account = $this->accounts->findByEmployerNumber($query->tenantId, $query->employer->number());
        if ($account instanceof EmployerAccount && $account->employer()->equals($query->employer)) {
            $this->refresher->apply($account, $verification);
        }

        return EmployerStatusView::fromVerification($verification, Instant::fromDateTime($this->clock->now()));
    }
}
