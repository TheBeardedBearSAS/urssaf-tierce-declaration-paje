<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\MandateRegistrar;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateRecorder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;

/**
 * Method 030 for a tracked employer. A local active mandate means there is nothing to send: it is returned without any call,
 * and when the account stage does not follow it yet ("mandate_registered"), the account is completed without any call
 * (idempotent, the import path of the {@see MandateRecorder}: nothing is reported as accepted). Every failure of the call propagates and records nothing, whatever the cause (ADR 0003, ADR 0006):
 * - a rejection with {@see RejectionKind::MandateHeldElsewhere}
 *   (`COMPTE_DEJA_MANDATE`: another platform holds the mandate, api-reference §1.2): the host reports it as
 *   "mandate_held_elsewhere" and may import the mandate if it is in fact its own;
 * - an {@see OutcomeUnknown} (030 is never resent unless declared safe): an operator checks on pajemploi. If the mandate
 *   exists there, ImportMandate records it (no call); only if it does not, this use case may be called again;
 * - invalid credentials, temporary failures (the call provably did not reach URSSAF).
 *
 * Only {@see MandateRecordingFailed} ("mandate.recording_failed") means that URSSAF accepted 030: recover without any call
 * (ImportMandate), never call this use case again. Every other exception means that the call was not sent
 * (a validation failure, a missing account, {@see UrssafRequestRejected}, {@see UrssafAuthenticationFailed},
 * {@see UrssafUnavailable}, {@see RateLimitExceeded}) or that its outcome is unknown ({@see OutcomeUnknown}, an unexpected
 * response, any foreign throwable): check pajemploi before anything else.
 *
 * Once URSSAF accepted 030 the mandate is recorded without another call, whatever the other writers do (ADR 0006): the
 * {@see MandateRecorder} reads the account and the mandate again and applies the transition, up to
 * {@see MandateRecorder::MAX_ATTEMPTS} attempts, on "employer_account.concurrent_modification" (for example a status
 * refresh, which the per-employer lock of the host does not cover), "mandate.concurrent_modification" or
 * "mandate.duplicate_active". A mandate another writer already recorded is returned as is. When every attempt failed, or
 * the recording failed for another reason (a violation that is not a conflict, a storage failure, an account erased meanwhile), {@see MandateRecordingFailed}
 * is thrown with the original failure as previous exception: the mandate exists at URSSAF, never call this use case
 * again, record it with ImportMandate.
 *
 * Mandate commands of one employer should still be serialized by the host (a lock per employer). The rollback of a failed
 * attempt needs a transactional TransactionManager.
 */
final readonly class RegisterMandateHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private MandateRepository $mandates,
        private MandateRegistrar $registrar,
        private MandateRecorder $recorder,
    ) {}

    /**
     * @throws RecordNotFound             "employer_account.not_found" before the call (an account erased after the call is the previous exception of MandateRecordingFailed)
     * @throws OutcomeUnknown
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     * @throws MandateRecordingFailed     "mandate.recording_failed": 030 was accepted but could not be recorded (conflicts, or
     *                                    any other failure as previous exception), use ImportMandate
     */
    public function __invoke(RegisterMandate $command): MandateView
    {
        $account = $this->locator->locate($command->tenantId, $command->employer);
        $active = $this->mandates->findActive($account->tenantId(), $account->employerNumber());
        if ($active instanceof Mandate) {
            // Nothing to send. Complete the account when it does not follow the mandate yet: the import path, since no call
            // was made (a failure is never reported as an accepted 030).
            return MandateView::fromMandate(
                $account->stage() === EmployerStage::MandateRegistered ? $active : $this->recorder->recordImport($account->id()),
            );
        }

        $this->registrar->registerMandate($account->tenantId(), $account->employer());

        return MandateView::fromMandate($this->recorder->recordRegistration($account->id()));
    }
}
