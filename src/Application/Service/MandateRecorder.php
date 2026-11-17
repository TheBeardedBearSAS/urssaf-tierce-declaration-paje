<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use Closure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use Throwable;

/**
 * Records a mandate transition that URSSAF already accepted (030, 031) or that the host observed (import, cancellation seen
 * on pajemploi), without any call (ADR 0006).
 *
 * The per-employer lock of the host does not cover the status refresh (SyncEmployerStatus(es), VerifyEmployer storing a
 * snapshot): it can change the account between the read and the save. Each attempt therefore reads the account and the
 * mandate again in a new unit of work and applies the transition to what it reads; a conflict
 * ("employer_account.concurrent_modification", "mandate.concurrent_modification", "mandate.duplicate_active") starts the
 * next attempt, at most {@see self::MAX_ATTEMPTS}. A transition another writer already recorded is not applied again
 * (idempotent), and an account left behind by a mandate that is already recorded is completed: the account stage
 * "mandate_registered" follows an active mandate, "mandate_cancelled" follows a cancelled one (when no other mandate is
 * active).
 *
 * Failures. After a call to URSSAF ({@see self::recordRegistration()}, {@see self::recordCancellation()}) the
 * transition must not be lost: every failure that is not a conflict (a violation, a storage or outbox failure) and the
 * exhaustion of the attempts are thrown as {@see MandateRecordingFailed}, the original failure being its previous
 * exception, so that the host has one signal and a call-free recovery. A {@see RecordNotFound} (the account or the
 * mandate was erased after the accepted call) is wrapped too, with the code "employer_account.not_found" or
 * "mandate.not_found": only {@see MandateRecordingFailed} means that URSSAF accepted 030 / 031. {@see self::recordImport()}
 * makes no call: after the attempts it rethrows the last conflict, and any other failure (a {@see RecordNotFound}
 * included) as is.
 *
 * The rollback of a failed attempt needs a transactional TransactionManager: with the default
 * ImmediateTransactionManager the aggregates saved before the conflict stay saved, and the next attempt completes them
 * (the mandate exists but the account stage does not follow: it is repaired).
 */
final readonly class MandateRecorder
{
    public const MAX_ATTEMPTS = 3;

    private const CONFLICTS = [
        EmployerAccountRepository::CONCURRENT_MODIFICATION,
        MandateRepository::CONCURRENT_MODIFICATION,
        'mandate.duplicate_active',
    ];

    public function __construct(
        private MandateRepository $mandates,
        private EmployerAccountRepository $accounts,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * Method 030 was accepted: the employer holds a registered mandate and the account follows. A mandate another writer
     * already recorded is kept, and the account completed.
     *
     * @throws MandateRecordingFailed every attempt met a conflict, or an attempt failed, the account erased meanwhile included (the failure is the previous exception)
     */
    public function recordRegistration(EmployerAccountId $accountId): Mandate
    {
        return $this->attempts(
            Operation::RegisterMandate,
            fn(): Mandate => $this->unitOfWork->run(fn(RecordedEvents $events): Mandate => $this->registerOnce($accountId, MandateOrigin::Registered, $events)),
        );
    }

    /**
     * A mandate obtained outside the package is adopted (no call): the employer holds an imported mandate and the account
     * follows. Idempotent and complete: an active mandate that is already recorded is kept and the account stage is
     * completed when it does not follow it yet.
     *
     * @throws RecordNotFound     the account was erased meanwhile
     * @throws InvariantViolation the last conflict after {@see self::MAX_ATTEMPTS} attempts, or a violation that is not a conflict
     */
    public function recordImport(EmployerAccountId $accountId): Mandate
    {
        return $this->attempts(
            null,
            fn(): Mandate => $this->unitOfWork->run(fn(RecordedEvents $events): Mandate => $this->registerOnce($accountId, MandateOrigin::Imported, $events)),
        );
    }

    /**
     * Method 031 was accepted (or the cancellation was observed on pajemploi): the mandate is cancelled and the account
     * follows. A mandate another writer already cancelled is kept, and the account completed.
     *
     * @throws MandateRecordingFailed every attempt met a conflict, or an attempt failed, the account or the mandate erased meanwhile included (the failure is the previous exception)
     */
    public function recordCancellation(EmployerAccountId $accountId, MandateId $mandateId): Mandate
    {
        return $this->attempts(
            Operation::CancelMandate,
            fn(): Mandate => $this->unitOfWork->run(fn(RecordedEvents $events): Mandate => $this->cancelOnce($accountId, $mandateId, $events)),
        );
    }

    private function registerOnce(EmployerAccountId $accountId, MandateOrigin $origin, RecordedEvents $events): Mandate
    {
        $account = $this->accounts->get($accountId);
        $now = Instant::fromDateTime($this->clock->now());
        $active = $this->mandates->findActive($account->tenantId(), $account->employerNumber());
        if ($active instanceof Mandate) {
            // Another writer recorded the mandate: only the account may be left behind.
            if ($account->stage() !== EmployerStage::MandateRegistered) {
                $account->markMandateRegistered($now);
                $this->saveAccount($account, $events);
            }

            return $active;
        }

        $mandate = ($origin === MandateOrigin::Imported ? Mandate::import(...) : Mandate::register(...))(
            MandateId::fromString($this->ids->generate()),
            $account->tenantId(),
            $account->externalReference(),
            $account->employer(),
            $account->id(),
            $now,
        );
        $this->mandates->save($mandate);
        $events->record(...$mandate->releaseEvents());
        $account->markMandateRegistered($now);
        $this->saveAccount($account, $events);

        return $mandate;
    }

    private function cancelOnce(EmployerAccountId $accountId, MandateId $mandateId, RecordedEvents $events): Mandate
    {
        $account = $this->accounts->get($accountId);
        $mandate = $this->mandates->get($mandateId);
        $now = Instant::fromDateTime($this->clock->now());

        return $mandate->isActive()
            ? $this->cancelActive($account, $mandate, $now, $events)
            : $this->completeAccountOf($account, $mandate, $now, $events);
    }

    private function cancelActive(EmployerAccount $account, Mandate $mandate, Instant $now, RecordedEvents $events): Mandate
    {
        $mandate->cancel($now);
        $this->mandates->save($mandate);
        $events->record(...$mandate->releaseEvents());
        $account->markMandateCancelled($now);
        $this->saveAccount($account, $events);

        return $mandate;
    }

    /**
     * Another writer cancelled the mandate: only the account may be left behind (and only when no other mandate is active).
     */
    private function completeAccountOf(EmployerAccount $account, Mandate $mandate, Instant $now, RecordedEvents $events): Mandate
    {
        if (
            $account->stage() !== EmployerStage::MandateCancelled
            && !$this->mandates->findActive($account->tenantId(), $account->employerNumber()) instanceof Mandate
        ) {
            $account->markMandateCancelled($now);
            $this->saveAccount($account, $events);
        }

        return $mandate;
    }

    private function saveAccount(EmployerAccount $account, RecordedEvents $events): void
    {
        $this->accounts->save($account);
        $events->record(...$account->releaseEvents());
    }

    /**
     * @template T
     *
     * @param Closure(): T $attempt
     * @param-immediately-invoked-callable $attempt
     * @param Operation|null $operation the call URSSAF accepted, or null without a call (nothing is wrapped then)
     *
     * @return T
     *
     * @throws Throwable
     */
    private function attempts(?Operation $operation, Closure $attempt): mixed
    {
        $number = 0;
        while (true) {
            ++$number;
            try {
                return $attempt();
            } catch (Throwable $failure) {
                $code = $failure instanceof InvariantViolation || $failure instanceof RecordNotFound ? $failure->violationCode() : null;
                $conflict = $code !== null && \in_array($code, self::CONFLICTS, true);
                if ($conflict && $number < self::MAX_ATTEMPTS) {
                    continue;
                }
                if (!$operation instanceof Operation) {
                    throw $failure;
                }

                throw MandateRecordingFailed::after($operation, $number, $code ?? $failure::class, $failure);
            }
        }
    }
}
