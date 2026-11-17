<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use Closure;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\DeclarationRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryPreDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use Throwable;

/**
 * Claims a pending declaration record, optionally pre-declares it (045 / 046, policy "preDeclareBeforeDeclaring", ADR 0008),
 * sends the declaration (050 / 051) and settles the answer (ADR 0003, ADR 0006).
 *
 * The claim is atomic (compare-and-set pending -> submitting): a concurrent caller gets {@see ItemOutcome::NotClaimed}.
 * The URSSAF calls are made outside any transaction. Every answer is then settled in a transaction of its own against the
 * record **as re-read when the answer arrives**, the claim being identified by the attempt number it took (settlement
 * table of ADR 0006):
 * - accepted (200 of 050 / 051): recorded as declared on every surviving state but declared (late acceptance: reconciled
 *   meanwhile, resolved as not received, requeued, claimed again, rejected or already declared by a concurrent call); on a
 *   declared record the first result is kept. It is never discarded: a record that was erased, a save that fails for
 *   another reason or conflicts that outlast {@see self::MAX_RECORDING_ATTEMPTS} attempts are a
 *   {@see DeclarationRecordingFailed} (the call is made, it is never resent);
 * - any other answer only settles its own claim (state submitting, same attempt): on a record it no longer owns it changes
 *   nothing and the caller gets the re-entry outcome of the current state ({@see ItemOutcome::NotClaimed},
 *   {@see ItemOutcome::Replayed}), without any exception. On an owned record:
 *   - rejected (final), already declared (duplicate, final), invalid credentials: returned;
 *   - 5xx, transport error or unreadable answer on 050 / 051 ({@see OutcomeUnknown}): outcome unknown, never resent
 *     automatically;
 *   - failure proven not sent (429, token endpoint, local rate limit; on a pre-declaration any retryable failure): error,
 *     then pending again while attempts remain ({@see RetryableUrssafFailure} thrown after the commit) or
 *     {@see ItemOutcome::Exhausted} returned. A failed pre-declaration uses one submission attempt;
 *   - any other package exception: error (pending again while attempts remain), rethrown unchanged;
 *   - any other failure (a bug, a foreign gateway): outcome unknown on 050 / 051 (it may have been sent), error on a
 *     pre-declaration, rethrown unchanged.
 * A pre-declaration records nothing at URSSAF: it never gives an unknown outcome and its answers are never "accepted".
 * Messages of port exceptions carry no personal data and a foreign exception's message is never stored (only its class).
 *
 * One private method per outcome of the settlement table, hence the method count and the complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class DeclarationSubmitter
{
    public const UNAVAILABLE = 'urssaf.unavailable';
    public const UNEXPECTED_RESPONSE = 'urssaf.unexpected_response';
    public const UNEXPECTED_FAILURE = 'urssaf.unexpected_failure';
    public const REJECTED_WITHOUT_ERRORS = 'urssaf.rejected';
    public const MAX_RECORDING_ATTEMPTS = 3;

    public function __construct(
        private DeclarationRecordRepository $records,
        private SalaryPreDeclarer $preDeclarer,
        private SalaryDeclarer $declarer,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws Throwable {@see RetryableUrssafFailure} when the call provably did not reach URSSAF (the record is back to
     *                   pending, committed); any unexpected failure otherwise, rethrown unchanged after the outcome was
     *                   committed (an {@see InvariantViolation} "declaration_submission.unsupported" for a declaration that is
     *                   neither a childminder nor a home care declaration: nothing was sent, the record is pending again)
     */
    public function submit(
        DeclarationRecordId $id,
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        Declaration $declaration,
    ): DeclarationSubmission {
        if (!$this->records->claimForSubmission($id, Instant::fromDateTime($this->clock->now()))) {
            return new DeclarationSubmission(
                DeclarationView::fromRecord($this->records->get($id)),
                ItemOutcome::NotClaimed,
            );
        }
        $record = $this->records->get($id);
        if ($this->policy->preDeclareBeforeDeclaring()) {
            try {
                $this->preDeclare($tenantId, $employer, $declaration);
            } catch (Throwable $failure) {
                return $this->settleFailure($record, $failure, SubmissionStage::PreDeclaration);
            }
        }
        try {
            $result = $this->declare($tenantId, $employer, $declaration);
        } catch (Throwable $failure) {
            return $this->settleFailure($record, $failure, SubmissionStage::Declaration);
        }

        return $this->declared($record, $result);
    }

    /**
     * An accepted 050 / 051 is never discarded: it is recorded on whichever state the record has when the answer arrives
     * (see {@see self::settle()}).
     */
    private function declared(DeclarationRecord $claimed, DeclarationResult $result): DeclarationSubmission
    {
        return $this->settle(
            $claimed,
            static function (DeclarationRecord $record, Instant $now) use ($result): ItemOutcome {
                $record->markDeclared($result, $now);

                return ItemOutcome::Declared;
            },
            $result,
        );
    }

    /**
     * @throws InvariantViolation         "declaration_submission.unsupported"
     * @throws OutcomeUnknown
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    private function declare(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        Declaration $declaration,
    ): DeclarationResult {
        return match (true) {
            $declaration instanceof ChildminderDeclaration => $this->declarer->declareChildminder($tenantId, $employer, $declaration),
            $declaration instanceof HomeCareDeclaration => $this->declarer->declareHomeCare($tenantId, $employer, $declaration),
            default => throw self::unsupported(),
        };
    }

    /**
     * @throws InvariantViolation         "declaration_submission.unsupported"
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    private function preDeclare(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        Declaration $declaration,
    ): PreDeclarationResult {
        return match (true) {
            $declaration instanceof ChildminderDeclaration => $this->preDeclarer->preDeclareChildminder($tenantId, $employer, $declaration),
            $declaration instanceof HomeCareDeclaration => $this->preDeclarer->preDeclareHomeCare($tenantId, $employer, $declaration),
            default => throw self::unsupported(),
        };
    }

    private static function unsupported(): InvariantViolation
    {
        return InvariantViolation::because(
            'declaration_submission.unsupported',
            'The declaration is neither a childminder nor a home care declaration.',
        );
    }

    /**
     * @throws Throwable {@see RetryableUrssafFailure}, or the failure itself when it is neither a business answer nor a
     *                   retryable one
     */
    private function settleFailure(DeclarationRecord $record, Throwable $failure, SubmissionStage $stage): DeclarationSubmission
    {
        return match (true) {
            $failure instanceof UrssafRequestRejected => $this->rejected($record, $failure),
            $failure instanceof UrssafAuthenticationFailed => $this->invalidCredentials($record),
            $stage->mayHaveRecorded() && $failure instanceof OutcomeUnknown => $this->unknown(
                $record,
                self::submissionErrors($failure->errors(), RejectionKind::Technical),
            ),
            $stage->mayHaveRecorded() && $failure instanceof UrssafUnavailable && $failure->mayHaveReachedUrssaf() => $this->unknown(
                $record,
                self::submissionErrors($failure->errors(), RejectionKind::Technical),
            ),
            default => $this->settleUnexpected($record, $failure, $stage),
        };
    }

    /**
     * A retryable failure that provably did not reach URSSAF, another package exception, or a failure of unknown origin.
     *
     * @throws Throwable
     */
    private function settleUnexpected(DeclarationRecord $record, Throwable $failure, SubmissionStage $stage): DeclarationSubmission
    {
        if ($failure instanceof RetryableException) {
            $submission = $this->requeue($record, $this->unavailableErrors($failure));

            return $submission->outcome === ItemOutcome::Exhausted || !self::settled($submission)
                ? $submission
                : throw RetryableUrssafFailure::causedBy($failure);
        }
        if ($failure instanceof UrssafException) {
            $submission = $this->requeue($record, [SubmissionError::of(self::UNEXPECTED_RESPONSE, RejectionKind::Unknown->value, $failure::class, null)]);

            return self::settled($submission) ? throw $failure : $submission;
        }
        $errors = [SubmissionError::of(self::UNEXPECTED_FAILURE, RejectionKind::Unknown->value, $failure::class, null)];
        $submission = $stage->mayHaveRecorded() ? $this->unknown($record, $errors) : $this->requeue($record, $errors);

        return self::settled($submission) ? throw $failure : $submission;
    }

    private function invalidCredentials(DeclarationRecord $record): DeclarationSubmission
    {
        return $this->settle($record, static function (DeclarationRecord $record, Instant $now): ItemOutcome {
            $record->markInvalidCredentials($now);

            return ItemOutcome::InvalidCredentials;
        }, null);
    }

    /**
     * @param list<SubmissionError> $errors
     */
    private function unknown(DeclarationRecord $record, array $errors): DeclarationSubmission
    {
        return $this->settle($record, static function (DeclarationRecord $record, Instant $now) use ($errors): ItemOutcome {
            $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, $errors, $now);

            return ItemOutcome::OutcomeUnknown;
        }, null);
    }

    /**
     * A duplicate declaration is final too, but it is a state of its own: URSSAF holds the declaration (ADR 0003).
     */
    private function rejected(DeclarationRecord $record, UrssafRequestRejected $rejection): DeclarationSubmission
    {
        return $this->settle($record, static function (DeclarationRecord $record, Instant $now) use ($rejection): ItemOutcome {
            $errors = self::submissionErrors($rejection->errors(), $rejection->kind());
            if ($rejection->is(RejectionKind::DuplicateDeclaration)) {
                $record->markAlreadyDeclared($errors, $now);

                return ItemOutcome::AlreadyDeclared;
            }
            $record->markRejected(
                $errors === []
                    ? [SubmissionError::of(
                        self::REJECTED_WITHOUT_ERRORS,
                        $rejection->kind()->value,
                        'URSSAF refused the declaration without error details.',
                        null,
                    )]
                    : $errors,
                $now,
            );

            return ItemOutcome::Rejected;
        }, null);
    }

    /**
     * submitting -> error, then pending again while attempts remain. {@see ItemOutcome::Exhausted} when they do not
     * (the record is left in error), {@see ItemOutcome::RetryLater} when it was requeued.
     *
     * @param list<SubmissionError> $errors
     */
    private function requeue(DeclarationRecord $record, array $errors): DeclarationSubmission
    {
        return $this->settle($record, function (DeclarationRecord $record, Instant $now) use ($errors): ItemOutcome {
            $record->markFailed($errors, $now);
            $maxAttempts = $this->policy->maxSubmissionAttempts();
            if (!$record->canRetry($maxAttempts)) {
                return ItemOutcome::Exhausted;
            }
            $record->retry($now, $maxAttempts);

            return ItemOutcome::RetryLater;
        }, null);
    }

    /**
     * @return list<SubmissionError>
     */
    private function unavailableErrors(RetryableException $failure): array
    {
        if ($failure instanceof UrssafUnavailable && !$failure->errors()->isEmpty()) {
            return self::submissionErrors($failure->errors(), RejectionKind::Technical);
        }

        return [SubmissionError::of(self::UNAVAILABLE, RejectionKind::Technical->value, $failure::class, null)];
    }

    /**
     * @return list<SubmissionError>
     */
    private static function submissionErrors(ApiErrors $errors, RejectionKind $kind): array
    {
        return array_map(
            static fn(ApiError $error): SubmissionError => SubmissionError::of(
                $error->code(),
                $kind->value,
                $error->message(),
                $error->description(),
            ),
            $errors->all(),
        );
    }

    /**
     * True when the answer changed the record (the claim was still its own, or the answer was accepted).
     */
    private static function settled(DeclarationSubmission $submission): bool
    {
        return $submission->outcome !== ItemOutcome::NotClaimed && $submission->outcome !== ItemOutcome::Replayed;
    }

    /**
     * Settles the answer of a call against the record as it is stored now, in one transaction (save, events), and
     * describes the record with the outcome. A conflict (another writer saved in between) re-reads and settles again,
     * at most {@see self::MAX_RECORDING_ATTEMPTS} times; the last conflict, or any other failure of the recording, is
     * rethrown unchanged. When the answer is accepted ($accepted) a failure of the recording is instead a
     * {@see DeclarationRecordingFailed}, the call being made and never resent.
     *
     * @param Closure(DeclarationRecord, Instant): ItemOutcome $transition
     * @param-immediately-invoked-callable $transition
     */
    private function settle(DeclarationRecord $claimed, Closure $transition, ?DeclarationResult $accepted): DeclarationSubmission
    {
        for ($attempt = 1; $attempt < self::MAX_RECORDING_ATTEMPTS; ++$attempt) {
            try {
                return $this->settleOnce($claimed, $transition, $accepted);
            } catch (Throwable $failure) {
                if (!self::isConflict($failure)) {
                    throw self::unrecorded($claimed, $accepted, $attempt, $failure);
                }
            }
        }

        try {
            return $this->settleOnce($claimed, $transition, $accepted);
        } catch (Throwable $failure) {
            throw self::unrecorded($claimed, $accepted, self::MAX_RECORDING_ATTEMPTS, $failure);
        }
    }

    private static function isConflict(Throwable $failure): bool
    {
        return $failure instanceof InvariantViolation
            && $failure->violationCode() === DeclarationRecordRepository::CONCURRENT_MODIFICATION;
    }

    /**
     * What the caller gets when the settlement failed: the failure itself, except for an accepted answer, which must
     * never be lost silently: the call is made and never resent.
     */
    private static function unrecorded(
        DeclarationRecord $claimed,
        ?DeclarationResult $accepted,
        int $attempts,
        Throwable $failure,
    ): Throwable {
        return $accepted instanceof DeclarationResult
            ? DeclarationRecordingFailed::after(
                $claimed->id(),
                $accepted->documentReference(),
                $attempts,
                $failure instanceof DomainException ? $failure->violationCode() : $failure::class,
                $failure,
            )
            : $failure;
    }

    /**
     * @param Closure(DeclarationRecord, Instant): ItemOutcome $transition
     */
    private function settleOnce(DeclarationRecord $claimed, Closure $transition, ?DeclarationResult $accepted): DeclarationSubmission
    {
        try {
            $record = $this->records->get($claimed->id());
        } catch (RecordNotFound $erased) {
            // Erased (ForgetEmployer): an accepted result has nowhere to go, any other answer has nothing to settle.
            return $accepted instanceof DeclarationResult
                ? throw $erased
                : new DeclarationSubmission(DeclarationView::fromRecord($claimed), ItemOutcome::NotClaimed);
        }
        if ($accepted instanceof DeclarationResult && $record->state() === DeclarationState::Declared) {
            // First result kept.
            return new DeclarationSubmission(DeclarationView::fromRecord($record), ItemOutcome::Declared);
        }
        if (!$accepted instanceof DeclarationResult && !self::owns($claimed, $record)) {
            return new DeclarationSubmission(DeclarationView::fromRecord($record), self::reentry($record->state()));
        }
        $outcome = $this->unitOfWork->run(function (RecordedEvents $events) use ($record, $transition): ItemOutcome {
            $outcome = $transition($record, Instant::fromDateTime($this->clock->now()));
            $this->records->save($record);
            $events->record(...$record->releaseEvents());

            return $outcome;
        });

        return new DeclarationSubmission(DeclarationView::fromRecord($record), $outcome);
    }

    /**
     * The claim is identified by the attempt number it took: it owns the record while it is submitting that attempt.
     */
    private static function owns(DeclarationRecord $claimed, DeclarationRecord $current): bool
    {
        return $current->state() === DeclarationState::Submitting && $current->attempts() === $claimed->attempts();
    }

    /**
     * What DeclareSalary would answer to the same declaration: in flight or waiting (not claimed), or already settled
     * (replayed).
     */
    private static function reentry(DeclarationState $state): ItemOutcome
    {
        return match ($state) {
            DeclarationState::Pending, DeclarationState::Submitting, DeclarationState::Error => ItemOutcome::NotClaimed,
            DeclarationState::Declared,
            DeclarationState::Rejected,
            DeclarationState::AlreadyDeclared,
            DeclarationState::OutcomeUnknown,
            DeclarationState::InvalidCredentials => ItemOutcome::Replayed,
        };
    }
}
