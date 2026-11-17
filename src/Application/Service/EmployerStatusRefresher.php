<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Reads the 020 snapshot of a tracked employer and stores it on the account, in one transaction (ADR 0006). Method 020
 * never changes the stage and says nothing about the mandate: only the snapshot and the instant it was read are kept.
 *
 * The call is made outside any transaction.
 */
final readonly class EmployerStatusRefresher
{
    public function __construct(
        private EmployerVerifier $verifier,
        private EmployerAccountRepository $accounts,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * True when the snapshot changed (an event was recorded).
     *
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     * @throws InvariantViolation         "employer_account.concurrent_modification": the account changed since it was read, nothing was saved
     */
    public function refresh(EmployerAccount $account): bool
    {
        return $this->apply($account, $this->verifier->verifyEmployer($account->tenantId(), $account->employer()));
    }

    /**
     * Stores an answer of 020 already read for this employer. True when the snapshot changed.
     */
    public function apply(EmployerAccount $account, EmployerVerification $verification): bool
    {
        return $this->unitOfWork->run(function (RecordedEvents $events) use ($account, $verification): bool {
            $account->applyVerification($verification, Instant::fromDateTime($this->clock->now()));
            $this->accounts->save($account);
            $released = $account->releaseEvents();
            $events->record(...$released);

            return $released !== [];
        });
    }
}
