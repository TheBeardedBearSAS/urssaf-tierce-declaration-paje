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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\MandateRegistrar;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateRecorder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;

/**
 * Method 031 for a tracked employer holding a local active mandate (record type "mandate": without one the command is
 * refused with no call; record it with ImportMandate first when it exists at URSSAF). Every failure of the call
 * propagates and cancels nothing, an {@see OutcomeUnknown} included (ADR 0003): an operator checks on pajemploi. If it
 * shows the mandate cancelled, RecordMandateCancellation records it (no call); only if the mandate is still active, call
 * this use case again.
 *
 * Only {@see MandateRecordingFailed} ("mandate.recording_failed") means that URSSAF accepted 031: recover without any call
 * (RecordMandateCancellation), never call this use case again. Every other exception means that the call was not sent
 * (a missing account or mandate, {@see UrssafRequestRejected}, {@see UrssafAuthenticationFailed}, {@see UrssafUnavailable},
 * {@see RateLimitExceeded}) or that its outcome is unknown ({@see OutcomeUnknown}, an unexpected response, any foreign
 * throwable): check pajemploi before anything else.
 *
 * Once URSSAF accepted 031 the cancellation is recorded without another call, whatever the other writers do (ADR 0006):
 * the {@see MandateRecorder} reads the account and the mandate again and applies the transition, up to
 * {@see MandateRecorder::MAX_ATTEMPTS} attempts, on "employer_account.concurrent_modification" (for example a status
 * refresh, which the per-employer lock of the host does not cover), "mandate.concurrent_modification" or
 * "mandate.duplicate_active". A cancellation another writer already recorded is returned as is. When every attempt
 * failed, or the recording failed for another reason (a violation that is not a conflict, a storage failure, an account or mandate erased meanwhile),
 * {@see MandateRecordingFailed} is thrown with the original failure as previous exception: the mandate is cancelled at
 * URSSAF, never call this use case again, record it with RecordMandateCancellation.
 *
 * Mandate commands of one employer should still be serialized by the host (a lock per employer). The rollback of a failed
 * attempt needs a transactional TransactionManager.
 */
final readonly class CancelMandateHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private MandateRepository $mandates,
        private MandateRegistrar $registrar,
        private MandateRecorder $recorder,
    ) {}

    /**
     * @throws RecordNotFound             "mandate.not_found" without an active local mandate, "employer_account.not_found" before the call (an account or mandate erased after the call is the previous exception of MandateRecordingFailed)
     * @throws OutcomeUnknown
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     * @throws MandateRecordingFailed     "mandate.recording_failed": 031 was accepted but could not be recorded (conflicts, or
     *                                    any other failure as previous exception), use RecordMandateCancellation
     */
    public function __invoke(CancelMandate $command): MandateView
    {
        $account = $this->locator->locate($command->tenantId, $command->employer);
        $mandate = $this->mandates->findActive($account->tenantId(), $account->employerNumber())
            ?? throw RecordNotFound::withIdentifier('mandate', $account->id()->value());

        $this->registrar->cancelMandate($account->tenantId(), $mandate->employer());

        return MandateView::fromMandate($this->recorder->recordCancellation($account->id(), $mandate->id()));
    }
}
