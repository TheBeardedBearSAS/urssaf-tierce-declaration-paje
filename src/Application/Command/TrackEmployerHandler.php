<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Method 020 (a safe read), then the account is created with its snapshot. The same external reference with the same
 * employer returns the tracked account, with no call; the same reference with another employer, or an employer tracked
 * under another reference, is refused with no call. Every failure of the call propagates and stores nothing.
 */
final readonly class TrackEmployerHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private EmployerAccountRepository $accounts,
        private MandateRepository $mandates,
        private EmployerVerifier $verifier,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws EmployerAlreadyTracked
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(TrackEmployer $command): EmployerAccountView
    {
        $existing = $this->locator->findExisting($command->tenantId, $command->externalReference, $command->employer);
        if ($existing instanceof EmployerAccount) {
            return EmployerAccountView::fromAccount($existing, $this->mandates->findActive($command->tenantId, $existing->employerNumber()));
        }

        $verification = $this->verifier->verifyEmployer($command->tenantId, $command->employer);

        $account = $this->unitOfWork->run(function (RecordedEvents $events) use ($command, $verification): EmployerAccount {
            $now = Instant::fromDateTime($this->clock->now());
            $account = EmployerAccount::track(
                EmployerAccountId::fromString($this->ids->generate()),
                $command->tenantId,
                $command->externalReference,
                $command->employer,
                $now,
            );
            $account->applyVerification($verification, $now);
            $this->accounts->save($account);
            $events->record(...$account->releaseEvents());

            return $account;
        });

        return EmployerAccountView::fromAccount($account, null);
    }
}
