<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarations;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\DeclarationRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\FailingOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\RecordingFailureReporter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;
use Throwable;

/**
 * The settlement table of ADR 0006 (lot 4b): the answer of 050 is settled against the record as it is stored when the
 * answer arrives, the claim being identified by its attempt number. One data set per cell of the table (row = the state
 * the record has when the answer arrives, column = the answer), each with a rolling-back transaction manager and with
 * ImmediateTransactionManager, ending with a follow-up DeclareSalary of the same reference and the exact list of the
 * gateway calls: URSSAF never accepts two calls.
 *
 * The "other writer" is a process that changes the stored record while the call is in flight (a reconciliation, an
 * operator, a concurrent requeue or call).
 *
 * Table-driven: one data set per cell, hence the method count and the complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DeclarationSettlementTest extends TestCase
{
    private const TENANT_REFERENCE = 'decl-ref-1';

    // Column 1: accepted (200 of 050)

    /**
     * Rows of the table whose accepted answer ends declared, by the state the other writer left: the claim's own state
     * (submitting, same attempt) comes first. Declared keeps the first result.
     *
     * @return iterable<string, array{string, bool, string, int}>
     */
    public static function acceptedCells(): iterable
    {
        $cells = [
            'submitting, same attempt (own claim)' => ['own', 'REF-OURS', 1],
            'outcome_unknown (reconciled meanwhile)' => ['outcome_unknown', 'REF-OURS', 1],
            'pending (resolved as not received)' => ['pending', 'REF-OURS', 1],
            'error (requeued then failed)' => ['error', 'REF-OURS', 1],
            'invalid_credentials' => ['invalid_credentials', 'REF-OURS', 1],
            'submitting, later attempt (claimed again)' => ['submitting_later', 'REF-OURS', 2],
            'already_declared (late acceptance)' => ['already_declared', 'REF-OURS', 1],
            'rejected (late acceptance)' => ['rejected', 'REF-OURS', 1],
            'declared (first result kept)' => ['declared', 'REF-FIRST', 1],
        ];
        foreach ($cells as $name => [$row, $reference, $attempts]) {
            yield $name . ', rolling back' => [$row, true, $reference, $attempts];
            yield $name . ', immediate' => [$row, false, $reference, $attempts];
        }
    }

    #[DataProvider('acceptedCells')]
    public function testAnAcceptedAnswerIsRecordedAsDeclaredOnEverySurvivingState(string $row, bool $transactional, string $reference, int $attempts): void
    {
        $world = $this->worldWithPendingRecord($transactional);
        $world->gateway->queue('050', RecordSamples::result('REF-OURS'));
        $this->otherWriterDuringTheCall($world, $row);

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('declared', $submission->declaration->status->state);
        static::assertSame($reference, $submission->declaration->status->result?->documentReference);
        $record = $this->stored($world);
        static::assertSame(DeclarationState::Declared, $record->state());
        static::assertSame($reference, $record->result()?->documentReference());
        static::assertSame($attempts, $record->attempts());
        static::assertSame([], $record->errors());
        static::assertNull($record->outcomeUnknownReason());
        static::assertSame($row === 'declared' ? [] : ['DeclarationDeclared'], $world->publisher->names());
        static::assertSame(['050'], $world->gateway->calls());

        $again = $this->declare($world);

        static::assertSame(ItemOutcome::Replayed, $again->outcome);
        static::assertSame($reference, $again->declaration->status->result?->documentReference);
        static::assertSame(['050'], $world->gateway->calls());
    }

    // Row 7: erased (ForgetEmployer)

    /**
     * @return iterable<string, array{bool}>
     */
    public static function managers(): iterable
    {
        yield 'rolling back' => [true];
        yield 'immediate' => [false];
    }

    #[DataProvider('managers')]
    public function testAnAcceptedAnswerOnAnErasedRecordIsARecordingFailureCarryingTheReference(bool $transactional): void
    {
        $world = $this->worldWithPendingRecord($transactional);
        $world->gateway->queue('050', RecordSamples::result('REF-OURS'));
        $this->otherWriterDuringTheCall($world, 'erased');

        $failure = $this->recordingFailure($world);

        static::assertSame('REF-OURS', $failure->documentReference());
        static::assertSame('declaration_record.recording_failed', $failure->violationCode());
        static::assertSame(['record_id' => 'record-1', 'attempts' => 1, 'last_code' => 'declaration_record.not_found', 'document_reference' => 'REF-OURS'], $failure->params());
        static::assertSame([], $world->records->inner()->all());
        static::assertSame([], $world->publisher->names());
        static::assertSame(['050'], $world->gateway->calls());

        // There is no local record left: a follow-up DeclareSalary records the declaration anew and calls URSSAF again
        // (it is the host's decision to send it; URSSAF answers the duplicate code if it holds the declaration).
        $world->gateway->queue('050', RecordSamples::result('REF-SECOND'));
        $again = $this->declare($world);

        static::assertSame(ItemOutcome::Declared, $again->outcome);
        static::assertSame('REF-SECOND', $again->declaration->status->result?->documentReference);
        static::assertSame(['050', '050'], $world->gateway->calls());
    }

    // Recovery of an accepted answer that could not be recorded

    #[DataProvider('managers')]
    public function testAStorageFailureAfterAnAcceptedAnswerIsRecoveredByReconcileThenResolve(bool $transactional): void
    {
        $world = $this->worldWithPendingRecord($transactional);
        $world->gateway->queue('050', RecordSamples::result('REF-OURS'));
        $storage = new RuntimeException('storage down');
        $world->records->beforeNextSave(static function () use ($storage): never {
            throw $storage;
        });

        $failure = $this->recordingFailure($world);

        static::assertSame('REF-OURS', $failure->documentReference());
        static::assertSame($storage, $failure->getPrevious());
        static::assertSame(['record_id' => 'record-1', 'attempts' => 1, 'last_code' => RuntimeException::class, 'document_reference' => 'REF-OURS'], $failure->params());
        static::assertSame(DeclarationState::Submitting, $this->stored($world)->state());
        static::assertSame(['050'], $world->gateway->calls());

        $world->clock->moveTo('2026-11-17T09:16:00Z');
        $report = (new ReconcileStaleDeclarationsHandler($world->records, $world->clock, $world->unitOfWork, $world->policy))(new ReconcileStaleDeclarations(null));
        static::assertEquals([new BatchItem('record-1', ItemOutcome::ManualReview, ['stale_submission'])], $report->items());
        static::assertSame(DeclarationState::OutcomeUnknown, $this->stored($world)->state());
        $resolved = (new ResolveDeclarationOutcomeHandler($world->locator, $world->records, $world->clock, $world->unitOfWork))(
            ResolveDeclarationOutcome::asDeclared(
                DeclarationWorld::tenant(),
                DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1')),
                'REF-OURS',
            ),
        );

        static::assertSame('declared', $resolved->status->state);
        static::assertSame('REF-OURS', $resolved->status->result?->documentReference);
        $again = $this->declare($world);
        static::assertSame(ItemOutcome::Replayed, $again->outcome);
        static::assertSame(['050'], $world->gateway->calls());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function managerNames(): iterable
    {
        yield 'rolling back' => ['rolling back'];
        yield 'immediate' => ['immediate'];
    }

    #[DataProvider('managerNames')]
    public function testAnOutboxFailureAfterAnAcceptedAnswerIsARecordingFailureAndNeverResends(string $manager): void
    {
        $transactional = $manager === 'rolling back';
        $world = $this->worldWithPendingRecord($transactional);
        $world->gateway->queue('050', RecordSamples::result('REF-OURS'), RecordSamples::result('REF-SECOND'));
        $storage = new RuntimeException('outbox down');
        $unitOfWork = new UnitOfWork(
            $transactional ? $world->transactions : new ImmediateTransactionManager(),
            $world->publisher,
            new RecordingFailureReporter(),
            new FailingOutbox($storage),
        );

        $failure = $this->recordingFailureWith($world, $unitOfWork);

        static::assertSame('REF-OURS', $failure->documentReference());
        static::assertSame($storage, $failure->getPrevious());
        static::assertSame(['record_id' => 'record-1', 'attempts' => 1, 'last_code' => RuntimeException::class, 'document_reference' => 'REF-OURS'], $failure->params());
        static::assertSame(['050'], $world->gateway->calls());
        // Rolled back: the claim is still submitting (reconcile then resolve, see the storage failure above). Immediate:
        // the record was saved before the outbox failed, it is declared (only its integration events are lost).
        static::assertSame($transactional ? DeclarationState::Submitting : DeclarationState::Declared, $this->stored($world)->state());

        $again = $this->declare($world);

        static::assertSame($transactional ? ItemOutcome::NotClaimed : ItemOutcome::Replayed, $again->outcome);
        static::assertSame(['050'], $world->gateway->calls());
    }

    #[DataProvider('managers')]
    public function testAnAcceptedAnswerThatKeepsMeetingConflictsGivesUpAfterThreeAttempts(bool $transactional): void
    {
        $world = $this->worldWithPendingRecord($transactional);
        $world->gateway->queue('050', RecordSamples::result('REF-OURS'));
        $world->gateway->duringNextCall(fn() => $this->touchBeforeEachSave($world));

        $failure = $this->recordingFailure($world);

        static::assertSame('REF-OURS', $failure->documentReference());
        static::assertSame(['record_id' => 'record-1', 'attempts' => 3, 'last_code' => 'declaration_record.concurrent_modification', 'document_reference' => 'REF-OURS'], $failure->params());
        static::assertSame(['050'], $world->gateway->calls());
        static::assertCount(3, array_keys($world->journal->entries(), 'save:record-1', true));
        static::assertSame(DeclarationState::OutcomeUnknown, $this->stored($world)->state());
        static::assertSame([], $world->publisher->names());
    }

    // Columns 2 to 5: the answers that are not an acceptance, first on the claim's own record

    /**
     * @return iterable<string, array{string, bool, array{outcome: ?ItemOutcome, state: string, thrown: ?class-string<Throwable>, follow: ItemOutcome, calls: list<string>}}>
     */
    public static function ownClaimCells(): iterable
    {
        $own = [
            'duplicate' => ['outcome' => ItemOutcome::AlreadyDeclared, 'state' => 'already_declared', 'thrown' => null, 'follow' => ItemOutcome::Replayed, 'calls' => ['050']],
            'rejected' => ['outcome' => ItemOutcome::Rejected, 'state' => 'rejected', 'thrown' => null, 'follow' => ItemOutcome::Declared, 'calls' => ['050', '050']],
            'unknown' => ['outcome' => ItemOutcome::OutcomeUnknown, 'state' => 'outcome_unknown', 'thrown' => null, 'follow' => ItemOutcome::Replayed, 'calls' => ['050']],
            'throttled' => ['outcome' => null, 'state' => 'pending', 'thrown' => RetryableUrssafFailure::class, 'follow' => ItemOutcome::Declared, 'calls' => ['050', '050']],
            'token' => ['outcome' => null, 'state' => 'pending', 'thrown' => RetryableUrssafFailure::class, 'follow' => ItemOutcome::Declared, 'calls' => ['050', '050']],
            'rate_limit' => ['outcome' => null, 'state' => 'pending', 'thrown' => RetryableUrssafFailure::class, 'follow' => ItemOutcome::Declared, 'calls' => ['050', '050']],
            'forbidden' => ['outcome' => ItemOutcome::InvalidCredentials, 'state' => 'invalid_credentials', 'thrown' => null, 'follow' => ItemOutcome::Replayed, 'calls' => ['050']],
            'foreign' => ['outcome' => null, 'state' => 'outcome_unknown', 'thrown' => RuntimeException::class, 'follow' => ItemOutcome::Replayed, 'calls' => ['050']],
            'credentials' => ['outcome' => ItemOutcome::InvalidCredentials, 'state' => 'invalid_credentials', 'thrown' => null, 'follow' => ItemOutcome::Replayed, 'calls' => ['050']],
        ];
        foreach (['rolling back' => true, 'immediate' => false] as $manager => $transactional) {
            foreach ($own as $answer => $expected) {
                yield "submitting, same attempt (own claim) x {$answer}, {$manager}" => [$answer, $transactional, $expected];
            }
        }
    }

    /**
     * @param array{outcome: ?ItemOutcome, state: string, thrown: ?class-string<Throwable>, follow: ItemOutcome, calls: list<string>} $expected
     */
    #[DataProvider('ownClaimCells')]
    public function testAnyOtherAnswerSettlesTheOwnClaim(string $answer, bool $transactional, array $expected): void
    {
        $world = $this->worldWithPendingRecord($transactional);
        $world->gateway->queue('050', self::answer($answer), RecordSamples::result('REF-SECOND'));

        [$submission, $thrown] = $this->declareAndCatch($world);

        static::assertSame($expected['thrown'], $thrown instanceof Throwable ? $thrown::class : null);
        static::assertSame($expected['outcome'], $submission?->outcome);
        static::assertSame($expected['state'], $this->stored($world)->state()->value);
        static::assertSame(['050'], $world->gateway->calls());

        $again = $this->declare($world);

        static::assertSame($expected['follow'], $again->outcome);
        static::assertSame($expected['calls'], $world->gateway->calls());
    }

    // Columns 2 to 5 on a record the claim no longer owns: nothing changes

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function unownedCells(): iterable
    {
        $rows = [
            'outcome_unknown',
            'pending',
            'error',
            'invalid_credentials',
            'submitting_later',
            'already_declared',
            'rejected',
            'declared',
            'erased',
        ];
        foreach (['rolling back' => true, 'immediate' => false] as $manager => $transactional) {
            foreach ($rows as $row) {
                foreach (['duplicate', 'rejected', 'unknown', 'throttled', 'credentials', 'token', 'rate_limit', 'forbidden', 'foreign'] as $answer) {
                    yield "{$row} x {$answer}, {$manager}" => [$row, $answer, $transactional];
                }
            }
        }
    }

    #[DataProvider('unownedCells')]
    public function testAnyOtherAnswerOnARecordItNoLongerOwnsChangesNothing(string $row, string $answer, bool $transactional): void
    {
        $world = $this->worldWithPendingRecord($transactional);
        // Without a record, the follow-up DeclareSalary records the declaration anew: URSSAF answers it with the duplicate code.
        $world->gateway->queue('050', self::answer($answer), $row === 'erased' ? self::answer('duplicate') : RecordSamples::result('REF-SECOND'));
        $this->otherWriterDuringTheCall($world, $row);
        // [outcome returned (the re-entry outcome of the state), state left, outcome of the follow-up DeclareSalary, gateway calls at the end]
        [$outcome, $state, $follow, $calls] = match ($row) {
            'outcome_unknown' => [ItemOutcome::Replayed, 'outcome_unknown', ItemOutcome::Replayed, ['050']],
            'pending' => [ItemOutcome::NotClaimed, 'pending', ItemOutcome::Declared, ['050', '050']],
            'error' => [ItemOutcome::NotClaimed, 'error', ItemOutcome::Declared, ['050', '050']],
            'invalid_credentials' => [ItemOutcome::Replayed, 'invalid_credentials', ItemOutcome::Replayed, ['050']],
            'submitting_later' => [ItemOutcome::NotClaimed, 'submitting', ItemOutcome::NotClaimed, ['050']],
            'already_declared' => [ItemOutcome::Replayed, 'already_declared', ItemOutcome::Replayed, ['050']],
            'rejected' => [ItemOutcome::Replayed, 'rejected', ItemOutcome::Declared, ['050', '050']],
            'declared' => [ItemOutcome::Replayed, 'declared', ItemOutcome::Replayed, ['050']],
            default => [ItemOutcome::NotClaimed, 'submitting', ItemOutcome::AlreadyDeclared, ['050', '050']],
        };

        $submission = $this->declare($world);

        static::assertSame($outcome, $submission->outcome);
        static::assertSame($state, $submission->declaration->status->state);
        static::assertSame(['claim:record-1', 'call:050'], $world->journal->entries());
        static::assertSame([], $world->publisher->names());
        static::assertSame(['050'], $world->gateway->calls());
        $this->assertLeftAsTheOtherWriterLeftIt($world, $row);

        $again = $this->declare($world);

        static::assertSame($follow, $again->outcome);
        if ($row === 'erased') {
            static::assertSame('already_declared', $again->declaration->status->state);
        }
        static::assertSame($calls, $world->gateway->calls());
    }

    private function assertLeftAsTheOtherWriterLeftIt(DeclarationWorld $world, string $row): void
    {
        if ($row === 'erased') {
            static::assertSame([], $world->records->inner()->all());

            return;
        }
        $record = $this->stored($world);
        static::assertSame($row === 'declared' ? 'REF-FIRST' : null, $record->result()?->documentReference());
        static::assertSame($row === 'submitting_later' ? 2 : 1, $record->attempts());
    }

    private static function answer(string $answer): Throwable
    {
        return match ($answer) {
            'duplicate' => UrssafRequestRejected::withErrors(
                Operation::DeclareChildminder,
                400,
                ApiErrors::of(ApiError::of('ER_API_050_0070', null, null)),
                RejectionKind::DuplicateDeclaration,
            ),
            'rejected' => UrssafRequestRejected::withErrors(
                Operation::DeclareChildminder,
                400,
                ApiErrors::of(ApiError::of('ER_API_050_0001', null, null)),
                RejectionKind::BusinessRule,
            ),
            'unknown' => OutcomeUnknown::fromUnavailable(UrssafUnavailable::withStatus(Operation::DeclareChildminder, 500, null, ApiErrors::none())),
            'throttled' => UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, null, ApiErrors::none()),
            'token' => UrssafUnavailable::duringAuthentication(Operation::DeclareChildminder, 503, null, null),
            'rate_limit' => RateLimitExceeded::forOperation(Operation::DeclareChildminder, 5),
            'forbidden' => UrssafAuthenticationFailed::withStatus(403),
            'foreign' => new RuntimeException('foreign gateway failure'),
            default => UrssafAuthenticationFailed::withStatus(401),
        };
    }

    private function worldWithPendingRecord(bool $transactional): DeclarationWorld
    {
        $world = new DeclarationWorld(new WorkflowPolicy(), $transactional);
        $world->record('record-1', DeclarationState::Pending, 0);

        return $world;
    }

    /**
     * Another writer changes the stored record while the 050 call is in flight (after it was journaled, before it
     * answers). "own" leaves the claim alone.
     */
    private function otherWriterDuringTheCall(DeclarationWorld $world, string $row): void
    {
        if ($row === 'own') {
            return;
        }
        $world->gateway->duringNextCall(static function () use ($world, $row): void {
            $repository = $world->records->inner();
            $id = DeclarationRecordId::fromString('record-1');
            if ($row === 'erased') {
                $repository->delete($id);

                return;
            }
            $now = Instant::fromIso8601(DeclarationWorld::NOW);
            $other = $repository->get($id);
            match ($row) {
                'outcome_unknown' => $other->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], $now),
                'pending' => self::resolvedAsNotReceived($other, $now),
                'error' => $other->markFailed(RecordSamples::errors(), $now),
                'invalid_credentials' => $other->markInvalidCredentials($now),
                'submitting_later' => self::claimedAgain($other, $now),
                'already_declared' => $other->markAlreadyDeclared(RecordSamples::errors(), $now),
                'rejected' => $other->markRejected(RecordSamples::errors(), $now),
                default => $other->markDeclared(RecordSamples::result('REF-FIRST'), $now),
            };
            $repository->save($other);
        });
    }

    private static function resolvedAsNotReceived(DeclarationRecord $record, Instant $now): void
    {
        $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], $now);
        $record->resolveAsNotReceived($now);
    }

    private static function claimedAgain(DeclarationRecord $record, Instant $now): void
    {
        self::resolvedAsNotReceived($record, $now);
        $record->startSubmission($now);
    }

    private function touchBeforeEachSave(DeclarationWorld $world): void
    {
        $world->records->beforeNextSave(function () use ($world): void {
            $other = $world->records->get(DeclarationRecordId::fromString('record-1'));
            if ($other->state() === DeclarationState::Submitting) {
                $other->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], Instant::fromIso8601(DeclarationWorld::NOW));
            }
            $world->records->inner()->save($other);
            $this->touchBeforeEachSave($world);
        });
    }

    private function stored(DeclarationWorld $world): DeclarationRecord
    {
        return $world->records->findByExternalReference(DeclarationWorld::tenant(), DeclarationWorld::reference(self::TENANT_REFERENCE))
            ?? $world->records->get(DeclarationRecordId::fromString('record-1'));
    }

    private function recordingFailure(DeclarationWorld $world): DeclarationRecordingFailed
    {
        return $this->recordingFailureWith($world, $world->unitOfWork);
    }

    private function recordingFailureWith(DeclarationWorld $world, UnitOfWork $unitOfWork): DeclarationRecordingFailed
    {
        try {
            $this->declareWith($world, $unitOfWork);
        } catch (DeclarationRecordingFailed $failure) {
            return $failure;
        }
        static::fail('Expected DeclarationRecordingFailed.');
    }

    /**
     * @return array{?DeclarationSubmission, ?Throwable}
     */
    private function declareAndCatch(DeclarationWorld $world): array
    {
        try {
            return [$this->declare($world), null];
        } catch (RuntimeException $failure) {
            return [null, $failure];
        }
    }

    private function declare(DeclarationWorld $world): DeclarationSubmission
    {
        return $this->declareWith($world, $world->unitOfWork);
    }

    private function declareWith(DeclarationWorld $world, UnitOfWork $unitOfWork): DeclarationSubmission
    {
        return (new DeclareSalaryHandler(
            $world->records,
            $world->accounts,
            new DeclarationSubmitter($world->records, $world->gateway, $world->gateway, $world->clock, $unitOfWork, $world->policy),
            $world->guard(),
            $world->ids,
            $world->clock,
            $unitOfWork,
            $world->policy,
        ))(new DeclareSalary(
            DeclarationWorld::tenant(),
            DeclarationWorld::reference(self::TENANT_REFERENCE),
            DeclarationWorld::employer(),
            RecordSamples::childminder(),
            false,
        ));
    }
}
