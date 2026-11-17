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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\DeclarationAlreadyRecorded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use Throwable;

/**
 * Declares a salary (methods 050 / 051) and returns what happened (ADR 0003, ADR 0006, ADR 0008, ADR 0009).
 *
 * The declaration is validated by its construction. Local refusals (idempotency conflict, mandate) come before any record or
 * claim. Re-entry for the same tenant and external reference, by state of the existing record:
 * - another content or another employer: {@see DeclarationAlreadyRecorded} (except a rejected record, which is
 *   superseded);
 * - pending: sent now (after the mandate guard); submitting: {@see ItemOutcome::NotClaimed}; declared, already
 *   declared, outcome unknown or invalid credentials: {@see ItemOutcome::Replayed}, nothing is sent;
 * - error: sent again when attempts remain (policy raised), else {@see ItemOutcome::Exhausted};
 * - rejected (URSSAF recorded nothing): deleted and recorded again, whatever the new content.
 * Concurrent calls (ADR 0006): the one that loses a race sends nothing and returns {@see ItemOutcome::NotClaimed} with
 * the record the winner holds, whether it hit the uniqueness constraint of the external reference while recording
 * ("declaration_record.duplicate_external_reference", its transaction is rolled back), found the record changed while
 * requeuing it ("declaration_record.concurrent_modification"), or lost the claim.
 * The record keeps the employer account id when the employer is tracked at that time.
 */
final readonly class DeclareSalaryHandler
{
    public function __construct(
        private DeclarationRecordRepository $records,
        private EmployerAccountRepository $accounts,
        private DeclarationSubmitter $submitter,
        private MandateGuard $guard,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws Throwable {@see DeclarationAlreadyRecorded}, {@see MandateRequired} (nothing was recorded nor sent),
     *                   {@see RetryableUrssafFailure} (the record is pending again), or an unexpected failure of the
     *                   call, rethrown after its outcome was recorded
     */
    public function __invoke(DeclareSalary $command): DeclarationSubmission
    {
        $snapshot = DeclarationSnapshot::fromDeclaration($command->employer->number(), $command->declaration);
        $existing = $this->records->findByExternalReference($command->tenantId, $command->externalReference);
        if (!$existing instanceof DeclarationRecord) {
            return $this->recordAndSubmit($command, $snapshot, null);
        }

        return $this->reenter($command, $snapshot, $existing);
    }

    /**
     * Re-entry for the same tenant and external reference (see the class documentation).
     */
    private function reenter(DeclareSalary $command, DeclarationSnapshot $snapshot, DeclarationRecord $existing): DeclarationSubmission
    {
        if ($existing->state() !== DeclarationState::Rejected && !self::isSameDeclaration($existing, $snapshot)) {
            throw DeclarationAlreadyRecorded::withExternalReference($command->externalReference, $existing->id());
        }

        return match ($existing->state()) {
            DeclarationState::Pending => $this->send($command, $existing->id()),
            DeclarationState::Submitting => $this->described($existing, ItemOutcome::NotClaimed),
            DeclarationState::Declared,
            DeclarationState::AlreadyDeclared,
            DeclarationState::OutcomeUnknown,
            DeclarationState::InvalidCredentials => $this->described($existing, ItemOutcome::Replayed),
            DeclarationState::Error => $existing->canRetry($this->policy->maxSubmissionAttempts())
                ? $this->requeueAndSend($command, $existing)
                : $this->described($existing, ItemOutcome::Exhausted),
            DeclarationState::Rejected => $this->recordAndSubmit($command, $snapshot, $existing),
        };
    }

    /**
     * The fingerprint leaves the employer out: the same content for another employer is another declaration.
     */
    private static function isSameDeclaration(DeclarationRecord $record, DeclarationSnapshot $snapshot): bool
    {
        return $record->hasSameContentAs($snapshot)
            && $record->snapshot()->employerNumber()->equals($snapshot->employerNumber());
    }

    private function described(DeclarationRecord $record, ItemOutcome $outcome): DeclarationSubmission
    {
        return new DeclarationSubmission(DeclarationView::fromRecord($record), $outcome);
    }

    private function send(DeclareSalary $command, DeclarationRecordId $id): DeclarationSubmission
    {
        $this->assertMandate($command);

        return $this->submitter->submit($id, $command->tenantId, $command->employer, $command->declaration);
    }

    private function requeueAndSend(DeclareSalary $command, DeclarationRecord $record): DeclarationSubmission
    {
        $this->assertMandate($command);

        try {
            $this->unitOfWork->run(function (RecordedEvents $events) use ($record): void {
                $record->retry(Instant::fromDateTime($this->clock->now()), $this->policy->maxSubmissionAttempts());
                $this->records->save($record);
                $events->record(...$record->releaseEvents());
            });
        } catch (InvariantViolation $violation) {
            if ($violation->violationCode() !== DeclarationRecordRepository::CONCURRENT_MODIFICATION) {
                throw $violation;
            }

            try {
                $current = $this->records->get($record->id());
            } catch (RecordNotFound) {
                // Erased (ForgetEmployer) by the concurrent writer: there is no record to describe, the conflict is the answer.
                throw $violation;
            }

            return $this->described($current, ItemOutcome::NotClaimed);
        }

        return $this->submitter->submit($record->id(), $command->tenantId, $command->employer, $command->declaration);
    }

    private function assertMandate(DeclareSalary $command): void
    {
        $this->guard->assertMandate(
            $command->tenantId,
            $command->employer->number(),
            match ($command->declaration->activityType()) {
                DeclarationActivity::Childminder => Operation::DeclareChildminder,
                DeclarationActivity::HomeCare => Operation::DeclareHomeCare,
            },
            $command->skipMandateCheck,
        );
    }

    /**
     * Records the declaration (superseding a rejected record) once the mandate guard accepted it, then sends it.
     */
    private function recordAndSubmit(
        DeclareSalary $command,
        DeclarationSnapshot $snapshot,
        ?DeclarationRecord $superseded,
    ): DeclarationSubmission {
        $this->assertMandate($command);
        $id = DeclarationRecordId::fromString($this->ids->generate());
        $accountId = $this->accounts->findByEmployerNumber($command->tenantId, $command->employer->number())?->id();

        try {
            $this->unitOfWork->run(function (RecordedEvents $events) use ($command, $snapshot, $superseded, $id, $accountId): void {
                if ($superseded instanceof DeclarationRecord) {
                    $this->records->delete($superseded->id());
                }
                $record = DeclarationRecord::record(
                    $id,
                    $command->tenantId,
                    $command->externalReference,
                    $snapshot,
                    Instant::fromDateTime($this->clock->now()),
                    $accountId,
                );
                $this->records->save($record);
                $events->record(...$record->releaseEvents());
            });
        } catch (InvariantViolation $violation) {
            $winner = $violation->violationCode() === DeclarationRecordRepository::DUPLICATE_EXTERNAL_REFERENCE
                ? $this->records->findByExternalReference($command->tenantId, $command->externalReference)
                : null;
            if (!$winner instanceof DeclarationRecord) {
                throw $violation;
            }

            return $this->described($winner, ItemOutcome::NotClaimed);
        }

        return $this->submitter->submit($id, $command->tenantId, $command->employer, $command->declaration);
    }
}
