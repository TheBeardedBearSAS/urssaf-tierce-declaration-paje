<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarations;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellationHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RequeueAfterInvalidCredentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateRecorder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\ConcurrentAccounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\ConcurrentMandates;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * Races between two writers (ADR 0006): the other writer saves between the read and the save of the use case. Each
 * scenario checks that the loser reports the conflict (an item outcome or the violation) and sends nothing twice.
 *
 * @mago-expect lint:too-many-methods
 */
final class ConcurrentWritersTest extends TestCase
{
    private const OTHER_FAILURE = 'other.failure';

    // DeclareSalary: concurrent first call

    public function testAConcurrentFirstCallForTheSameReferenceIsNotClaimedAndSendsNothing(): void
    {
        $world = new DeclarationWorld();
        $world->records->beforeNextSave(static function () use ($world): void {
            $world->record('winner-1', DeclarationState::Pending);
        });

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('winner-1', $submission->declaration->recordId);
        static::assertSame('pending', $submission->declaration->status->state);
        static::assertSame([], $world->gateway->calls());
        static::assertSame(['begin', 'save:new-1', 'rollback'], $world->journal->entries());
        static::assertSame(['winner-1'], array_map(static fn(DeclarationRecord $record): string => $record->id()->value(), $world->records->inner()->all()));
    }

    public function testAnotherViolationOfTheFirstSaveIsNotHidden(): void
    {
        $world = new DeclarationWorld();
        $world->records->beforeNextSave(static function (): never {
            throw InvariantViolation::because(self::OTHER_FAILURE, 'Failed.');
        });

        $this->assertViolation(self::OTHER_FAILURE, fn(): DeclarationSubmission => $this->declare($world));
        static::assertSame([], $world->gateway->calls());
    }

    public function testADuplicateReferenceThatVanishedIsNotHidden(): void
    {
        $world = new DeclarationWorld();
        $world->records->beforeNextSave(static function (): never {
            throw InvariantViolation::because('declaration_record.duplicate_external_reference', 'Duplicate.');
        });

        $this->assertViolation('declaration_record.duplicate_external_reference', fn(): DeclarationSubmission => $this->declare($world));
        static::assertSame([], $world->gateway->calls());
    }

    public function testASupersededRejectedRecordRecordedByAnotherCallFirstIsNotClaimed(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Rejected);
        $world->records->beforeNextSave(static function () use ($world): void {
            $world->records->inner()->delete(DeclarationRecordId::fromString('record-1'));
            $world->record('winner-1', DeclarationState::Pending);
        });

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('winner-1', $submission->declaration->recordId);
        static::assertSame([], $world->gateway->calls());
    }

    // DeclareSalary: requeue of a failed record

    public function testARecordRequeuedByAnotherCallIsNotClaimedAndSendsNothing(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Error, 2);
        $world->records->beforeNextSave(static function () use ($world): void {
            $other = $world->records->get(DeclarationRecordId::fromString('record-1'));
            $other->retry(Instant::fromIso8601(DeclarationWorld::NOW), 5);
            $world->records->inner()->save($other);
        });

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('pending', $submission->declaration->status->state);
        static::assertSame([], $world->gateway->calls());
        static::assertSame(['begin', 'save:record-1', 'rollback'], $world->journal->entries());
    }

    public function testARecordErasedByAnotherCallDuringTheRequeueRethrowsTheConflict(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Error, 2);
        $world->records->beforeNextSave(static function () use ($world): void {
            $other = $world->records->get(DeclarationRecordId::fromString('record-1'));
            $other->retry(Instant::fromIso8601(DeclarationWorld::NOW), 5);
            $world->records->inner()->save($other);
            $world->records->inner()->delete(DeclarationRecordId::fromString('record-1'));
        });

        $this->assertViolation('declaration_record.concurrent_modification', fn(): DeclarationSubmission => $this->declare($world));
        static::assertSame([], $world->gateway->calls());
    }

    public function testAnotherViolationOfTheRequeueIsNotHidden(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Error, 2);
        $world->records->beforeNextSave(static function (): never {
            throw InvariantViolation::because(self::OTHER_FAILURE, 'Failed.');
        });

        $this->assertViolation(self::OTHER_FAILURE, fn(): DeclarationSubmission => $this->declare($world));
        static::assertSame([], $world->gateway->calls());
    }

    // DeclareSalary: the record changes while the call is in flight: see DeclarationSettlementTest

    // ReconcileStaleDeclarations

    public function testAStaleRecordThatChangedMeanwhileIsSkippedAndReported(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Submitting);
        $world->recordWith('record-2', DeclarationState::Submitting, 1, RecordSamples::childminder(), 'ref-2', 'tenant-1', 'Y1869005780009');
        $world->records->beforeNextSave(static function () use ($world): void {
            $other = $world->records->get(DeclarationRecordId::fromString('record-1'));
            $other->markDeclared(RecordSamples::result(), Instant::fromIso8601(DeclarationWorld::NOW));
            $world->records->inner()->save($other);
        });

        $report = $this->reconcile($world);

        static::assertEquals(
            [
                new BatchItem('record-1', ItemOutcome::NotClaimed, ['declaration_record.concurrent_modification']),
                new BatchItem('record-2', ItemOutcome::ManualReview, ['stale_submission']),
            ],
            $report->items(),
        );
        static::assertSame(DeclarationState::Declared, $world->records->get(DeclarationRecordId::fromString('record-1'))->state());
        static::assertSame(DeclarationState::OutcomeUnknown, $world->records->get(DeclarationRecordId::fromString('record-2'))->state());
        static::assertSame([], $world->gateway->calls());
        static::assertSame(
            ['begin', 'save:record-1', 'rollback', 'begin', 'save:record-2', 'append:urssaf.paje.declaration.outcome_unknown', 'commit', 'publish:DeclarationOutcomeUnknown'],
            $world->journal->entries(),
        );
    }

    public function testAnotherViolationOfTheReconciliationIsNotHidden(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Submitting);
        $world->records->beforeNextSave(static function (): never {
            throw InvariantViolation::because(self::OTHER_FAILURE, 'Failed.');
        });

        $this->assertViolation(self::OTHER_FAILURE, fn(): BatchReport => $this->reconcile($world));
    }

    // ResolveDeclarationOutcome and RequeueAfterInvalidCredentials

    public function testResolvingARecordAnotherOperatorResolvedFirstIsRefused(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::OutcomeUnknown);
        $world->records->beforeNextSave(static function () use ($world): void {
            $other = $world->records->get(DeclarationRecordId::fromString('record-1'));
            $other->resolveAsNotReceived(Instant::fromIso8601(DeclarationWorld::NOW));
            $world->records->inner()->save($other);
        });
        $command = ResolveDeclarationOutcome::asDeclared(
            DeclarationWorld::tenant(),
            DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1')),
            'REF-1',
        );

        $this->assertViolation(
            'declaration_record.concurrent_modification',
            static fn(): DeclarationView => (new ResolveDeclarationOutcomeHandler($world->locator, $world->records, $world->clock, $world->unitOfWork))($command),
        );

        static::assertSame(DeclarationState::Pending, $world->records->get(DeclarationRecordId::fromString('record-1'))->state());
        static::assertSame(['begin', 'save:record-1', 'rollback'], $world->journal->entries());
        static::assertSame([], $world->publisher->names());
    }

    public function testRequeuingARecordAnotherProcessRequeuedFirstFailsAndRollsBackTheWholeTransaction(): void
    {
        $world = new DeclarationWorld();
        $world->recordWith('record-1', DeclarationState::InvalidCredentials, 1, RecordSamples::childminder(), 'ref-1', 'tenant-1', 'Y1869005780009');
        $world->recordWith('record-2', DeclarationState::InvalidCredentials, 1, RecordSamples::childminder(), 'ref-2', 'tenant-1', 'Y1869005780009');
        $world->records->beforeNextSave(static function () use ($world): void {
            // The first record is about to be saved requeued: another process requeues the second one.
            $other = $world->records->get(DeclarationRecordId::fromString('record-2'));
            $other->retry(Instant::fromIso8601(DeclarationWorld::NOW), 5);
            $world->records->inner()->save($other);
        });

        $this->assertViolation(
            'declaration_record.concurrent_modification',
            static fn(): RequeueReport => (new RequeueAfterInvalidCredentialsHandler($world->records, $world->clock, $world->unitOfWork, $world->policy))(
                new RequeueAfterInvalidCredentials(DeclarationWorld::tenant(), null),
            ),
        );

        static::assertSame(['begin', 'save:record-1', 'save:record-2', 'rollback'], $world->journal->entries());
        // Nothing is requeued by the failed call: the first record is back to invalid_credentials, the second one is the
        // other process' own requeue.
        static::assertSame(DeclarationState::InvalidCredentials, $world->records->get(DeclarationRecordId::fromString('record-1'))->state());
        static::assertSame(DeclarationState::Pending, $world->records->get(DeclarationRecordId::fromString('record-2'))->state());
        static::assertSame([], $world->publisher->names());
        static::assertSame([], $world->outbox->appended());
        static::assertSame([], $world->gateway->calls());
    }

    // Employer status

    public function testAnAccountChangedMeanwhileIsReportedAsRetryLaterAndTheRunGoesOn(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount('account-1', 'ref-1', 'Y0000000000001');
        $world->trackedAccount('account-2', 'ref-2', 'Y0000000000002');
        $world->gateway->queue('020', EmployerSamples::verification(), EmployerSamples::verification());
        $world->gateway->duringNextCall(static function () use ($world): void {
            $other = $world->accounts->get(EmployerAccountId::fromString('account-1'));
            $other->markMandateRegistered(Instant::fromIso8601(EmployerWorld::NOW));
            $world->accounts->save($other);
        });

        $report = (new SyncEmployerStatusesHandler($world->accounts, $world->refresher, new WorkflowPolicy()))(
            new SyncEmployerStatuses(EmployerWorld::tenant(), null),
        );

        static::assertEquals(
            [
                new BatchItem('account-1', ItemOutcome::RetryLater, ['employer_account.concurrent_modification']),
                new BatchItem('account-2', ItemOutcome::Changed),
            ],
            $report->items(),
        );
        static::assertSame(['020', '020'], $world->gateway->calls());
        $first = $world->accounts->get(EmployerAccountId::fromString('account-1'));
        static::assertSame(EmployerStage::MandateRegistered, $first->stage());
        static::assertNull($first->lastVerification());
    }

    public function testAnotherViolationOfTheStatusSaveIsNotHidden(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('020', EmployerSamples::verification());
        $world->gateway->duringNextCall(static function (): never {
            throw InvariantViolation::because(self::OTHER_FAILURE, 'Failed.');
        });

        $this->assertViolation(
            self::OTHER_FAILURE,
            static fn(): BatchReport => (new SyncEmployerStatusesHandler($world->accounts, $world->refresher, new WorkflowPolicy()))(
                new SyncEmployerStatuses(EmployerWorld::tenant(), null),
            ),
        );
    }

    public function testASingleStatusSyncOfAChangedAccountFailsAndStoresNothing(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('020', EmployerSamples::verification());
        $world->gateway->duringNextCall(static function () use ($world): void {
            $other = $world->accounts->get(EmployerAccountId::fromString('account-1'));
            $other->markMandateRegistered(Instant::fromIso8601(EmployerWorld::NOW));
            $world->accounts->save($other);
        });

        $this->assertViolation(
            'employer_account.concurrent_modification',
            static fn(): EmployerAccountView => (new SyncEmployerStatusHandler($world->locator, $world->refresher, $world->accounts, $world->mandates))(
                new SyncEmployerStatus(EmployerWorld::tenant(), EmployerWorld::selector()),
            ),
        );

        static::assertNull($world->accounts->get(EmployerAccountId::fromString('account-1'))->lastVerification());
        static::assertSame(['call:020', 'begin', 'rollback'], $world->journal->entries());
    }

    // Mandates: URSSAF accepted 030 / 031, the local state is recorded whatever the other writers do (ADR 0006)

    public function testAStatusRefreshRacingMethod030IsRetriedAndTheMandateIsStillRecorded(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('030', null);
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($world): void {
            self::refreshStatus($world);
        });

        $view = $this->registerMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('registered', $view->state);
        // The first attempt was rolled back with its mandate: the second one records the mandate of its own.
        static::assertSame('id-2', $view->mandateId);
        static::assertSame(['030'], $world->gateway->calls());
        $account = $world->accounts->get(EmployerAccountId::fromString('account-1'));
        static::assertSame(EmployerStage::MandateRegistered, $account->stage());
        static::assertNotNull($account->lastVerification());
        static::assertCount(1, $world->mandates->all());
        static::assertSame(['MandateRegistered', 'EmployerStageChanged'], $world->publisher->names());
        static::assertSame(['call:030', 'begin', 'rollback', 'begin'], \array_slice($world->journal->entries(), 0, 4));
        static::assertCount(2, array_keys($world->journal->entries(), 'begin', true));
    }

    public function testWithoutATransactionTheMandateOfAFailedAttemptStaysAndTheNextAttemptCompletesTheAccount(): void
    {
        $world = new EmployerWorld(false);
        $world->trackedAccount();
        $world->gateway->queue('030', null);
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($world): void {
            self::refreshStatus($world);
        });

        $view = $this->registerMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        // Nothing was rolled back: the mandate of the first attempt is the one that is returned.
        static::assertSame('id-1', $view->mandateId);
        static::assertSame(['030'], $world->gateway->calls());
        $account = $world->accounts->get(EmployerAccountId::fromString('account-1'));
        static::assertSame(EmployerStage::MandateRegistered, $account->stage());
        static::assertNotNull($account->lastVerification());
        static::assertCount(1, $world->mandates->all());
        // The events of the failed attempt were never recorded: only the account stage change is published.
        static::assertSame(['EmployerStageChanged'], $world->publisher->names());
    }

    public function testAMandateRecordedByAnotherWriterWhileRegisteringIsReturnedAsIs(): void
    {
        $world = new EmployerWorld();
        $account = $world->trackedAccount();
        $world->gateway->queue('030', null);
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function () use ($world, $account): void {
            $world->registeredMandate($account, 'other-mandate');
        });

        $view = $this->registerMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('other-mandate', $view->mandateId);
        static::assertSame(['030'], $world->gateway->calls());
        static::assertSame(['other-mandate'], array_map(static fn(Mandate $mandate): string => $mandate->id()->value(), $world->mandates->all()));
        static::assertSame([], $world->publisher->names());
        static::assertSame(['call:030', 'begin', 'rollback', 'begin', 'append:', 'commit'], $world->journal->entries());
    }

    public function testAnAccountLeftBehindByAnotherWriterWhileRegisteringIsCompleted(): void
    {
        $world = new EmployerWorld();
        $account = $world->trackedAccount();
        $world->gateway->queue('030', null);
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function () use ($world, $account): void {
            // The other writer saved the mandate but its account save has not happened yet.
            $mandate = Mandate::register(
                MandateId::fromString('other-mandate'),
                $account->tenantId(),
                $account->externalReference(),
                $account->employer(),
                $account->id(),
                Instant::fromIso8601(EmployerWorld::NOW),
            );
            $world->mandates->save($mandate);
        });

        $view = $this->registerMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('other-mandate', $view->mandateId);
        static::assertSame(['030'], $world->gateway->calls());
        static::assertSame(EmployerStage::MandateRegistered, $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        static::assertSame(['EmployerStageChanged'], $world->publisher->names());
    }

    public function testADuplicateActiveMandateIsRetriedLikeAnyConflict(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('030', null);
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function (): never {
            throw InvariantViolation::because('mandate.duplicate_active', 'Duplicate.');
        });

        $view = $this->registerMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('id-2', $view->mandateId);
        static::assertSame(['030'], $world->gateway->calls());
        static::assertSame(['MandateRegistered', 'EmployerStageChanged'], $world->publisher->names());
    }

    public function testAMandateConflictIsRetriedWhileRegistering(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('030', null);
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function (): never {
            throw InvariantViolation::because(MandateRepository::CONCURRENT_MODIFICATION, 'Conflict.');
        });

        $view = $this->registerMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('registered', $view->state);
        static::assertSame(['030'], $world->gateway->calls());
    }

    public function testRegistrationGivesUpAfterThreeAttemptsWithACallFreeRecovery(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('030', null);
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($world): void {
            self::refreshStatus($world);
        }, 99);

        $failure = $this->recordingFailure(
            fn(): MandateView => $this->registerMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector())),
        );

        static::assertSame('mandate.recording_failed', $failure->violationCode());
        static::assertSame(['operation' => '030', 'attempts' => 3, 'last_code' => 'employer_account.concurrent_modification'], $failure->params());
        static::assertInstanceOf(InvariantViolation::class, $failure->getPrevious());
        static::assertSame('employer_account.concurrent_modification', $failure->getPrevious()->violationCode());
        static::assertSame(['030'], $world->gateway->calls());
        static::assertCount(3, array_keys($world->journal->entries(), 'rollback', true));
        static::assertSame([], $world->publisher->names());

        // The call-free recovery records the mandate: the 030 call is never made again.
        static::assertSame([], $world->mandates->all());
        static::assertSame(EmployerStage::Tracked, $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        $imported = (new ImportMandateHandler($world->locator, $world->mandates, $world->recorder))(
            new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()),
        );
        static::assertSame('registered', $imported->state);
        static::assertSame('imported', $imported->origin);
        static::assertSame(['030'], $world->gateway->calls());
        static::assertSame(EmployerStage::MandateRegistered, $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        static::assertSame(['MandateRegistered', 'EmployerStageChanged'], $world->publisher->names());
    }

    public function testAnotherViolationWhileRegisteringIsNotRetriedAndIsReportedAsARecordingFailure(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('030', null);
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function (): never {
            throw InvariantViolation::because(self::OTHER_FAILURE, 'Failed.');
        }, 99);

        $failure = $this->recordingFailure(
            fn(): MandateView => $this->registerMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector())),
        );

        static::assertSame('mandate.recording_failed', $failure->violationCode());
        static::assertSame(['operation' => '030', 'attempts' => 1, 'last_code' => self::OTHER_FAILURE], $failure->params());
        static::assertInstanceOf(InvariantViolation::class, $failure->getPrevious());
        static::assertSame(self::OTHER_FAILURE, $failure->getPrevious()->violationCode());
        static::assertSame(['030'], $world->gateway->calls());
        static::assertSame(['call:030', 'begin', 'rollback'], $world->journal->entries());
        static::assertSame([], $world->mandates->all());
    }

    public function testAFailureOfTheStoreWhileRegisteringIsReportedAsARecordingFailure(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('030', null);
        $storage = new RuntimeException('Connection lost.');
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function () use ($storage): never {
            throw $storage;
        });

        $failure = $this->recordingFailure(
            fn(): MandateView => $this->registerMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector())),
        );

        static::assertSame(['operation' => '030', 'attempts' => 1, 'last_code' => RuntimeException::class], $failure->params());
        static::assertSame($storage, $failure->getPrevious());
        static::assertSame(['030'], $world->gateway->calls());
    }

    public function testAFailureOfTheStoreWhileCancellingIsReportedAsARecordingFailure(): void
    {
        $world = new EmployerWorld();
        $world->registeredMandate($world->trackedAccount());
        $world->gateway->queue('031', null);
        $storage = new RuntimeException('Connection lost.');
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($storage): never {
            throw $storage;
        });

        $failure = $this->recordingFailure(
            fn(): MandateView => $this->cancelMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector())),
        );

        static::assertSame(['operation' => '031', 'attempts' => 1, 'last_code' => RuntimeException::class], $failure->params());
        static::assertSame($storage, $failure->getPrevious());
        static::assertSame(['031'], $world->gateway->calls());
        // Rolled back: the mandate is still active, the account still follows it.
        static::assertTrue($world->mandates->get(MandateId::fromString('mandate-1'))->isActive());
        static::assertSame(EmployerStage::MandateRegistered, $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
    }

    public function testAnAccountErasedWhileRegisteringIsWrappedAsARecordingFailure(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $world->gateway->queue('030', null);
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($world): void {
            $world->accounts->delete(EmployerAccountId::fromString('account-1'));
        });

        try {
            $this->registerMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
            static::fail('Expected MandateRecordingFailed.');
        } catch (MandateRecordingFailed $failure) {
            static::assertSame(['operation' => '030', 'attempts' => 2, 'last_code' => 'employer_account.not_found'], $failure->params());
            static::assertInstanceOf(RecordNotFound::class, $failure->getPrevious());
        }
        static::assertSame(['030'], $world->gateway->calls());
    }

    public function testAStatusRefreshRacingAnImportIsRetriedAndTheMandateIsRecorded(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($world): void {
            self::refreshStatus($world);
        });

        $view = $this->importMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('id-2', $view->mandateId);
        static::assertSame('imported', $view->origin);
        static::assertSame([], $world->gateway->calls());
        static::assertSame(EmployerStage::MandateRegistered, $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        static::assertCount(1, $world->mandates->all());
        static::assertCount(1, array_keys($world->journal->entries(), 'rollback', true));
    }

    public function testAnImportGivesUpAfterThreeAttemptsWithTheLastConflict(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($world): void {
            self::refreshStatus($world);
        }, 99);

        $this->assertViolation(
            'employer_account.concurrent_modification',
            fn(): MandateView => $this->importMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector())),
        );

        static::assertCount(3, array_keys($world->journal->entries(), 'rollback', true));
        static::assertSame([], $world->mandates->all());
        static::assertSame([], $world->gateway->calls());
        static::assertSame([], $world->publisher->names());
    }

    public function testAnImportDoesNotRetryAnotherViolation(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function (): never {
            throw InvariantViolation::because(self::OTHER_FAILURE, 'Failed.');
        }, 99);

        $this->assertViolation(
            self::OTHER_FAILURE,
            fn(): MandateView => $this->importMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector())),
        );

        static::assertSame(['begin', 'rollback'], $world->journal->entries());
    }

    public function testAStatusRefreshRacingMethod031IsRetriedAndTheCancellationIsStillRecorded(): void
    {
        $world = new EmployerWorld();
        $world->registeredMandate($world->trackedAccount());
        $world->gateway->queue('031', null);
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($world): void {
            self::refreshStatus($world);
        });

        $view = $this->cancelMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('cancelled', $view->state);
        static::assertSame(['031'], $world->gateway->calls());
        $account = $world->accounts->get(EmployerAccountId::fromString('account-1'));
        static::assertSame(EmployerStage::MandateCancelled, $account->stage());
        static::assertNotNull($account->lastVerification());
        static::assertSame(['call:031', 'begin', 'rollback', 'begin'], \array_slice($world->journal->entries(), 0, 4));
        static::assertSame(['MandateCancelled', 'EmployerStageChanged'], $world->publisher->names());
    }

    public function testWithoutATransactionTheCancelledMandateOfAFailedAttemptStaysAndTheNextAttemptCompletesTheAccount(): void
    {
        $world = new EmployerWorld(false);
        $world->registeredMandate($world->trackedAccount());
        $world->gateway->queue('031', null);
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function () use ($world): void {
            self::refreshStatus($world);
        });

        $view = $this->cancelMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('cancelled', $view->state);
        static::assertSame(['031'], $world->gateway->calls());
        static::assertFalse($world->mandates->get(MandateId::fromString('mandate-1'))->isActive());
        $account = $world->accounts->get(EmployerAccountId::fromString('account-1'));
        static::assertSame(EmployerStage::MandateCancelled, $account->stage());
        static::assertNotNull($account->lastVerification());
        static::assertSame(['EmployerStageChanged'], $world->publisher->names());
    }

    public function testAMandateConflictIsRetriedWhileCancelling(): void
    {
        $world = new EmployerWorld();
        $world->registeredMandate($world->trackedAccount());
        $world->gateway->queue('031', null);
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function () use ($world): void {
            $world->mandates->save($world->mandates->get(MandateId::fromString('mandate-1')));
        });

        $view = $this->cancelMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('cancelled', $view->state);
        static::assertSame(['031'], $world->gateway->calls());
        static::assertSame(
            [
                'call:031',
                'begin',
                'rollback',
                'begin',
                'append:urssaf.paje.mandate.cancelled,urssaf.paje.employer.status_changed',
                'commit',
                'publish:MandateCancelled',
                'publish:EmployerStageChanged',
            ],
            $world->journal->entries(),
        );
    }

    public function testACancellationRecordedByAnotherWriterIsReturnedAsIs(): void
    {
        $world = new EmployerWorld();
        $world->registeredMandate($world->trackedAccount());
        $world->gateway->queue('031', null);
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function () use ($world): void {
            $now = Instant::fromIso8601(EmployerWorld::NOW);
            $other = $world->mandates->get(MandateId::fromString('mandate-1'));
            $other->cancel($now);
            $world->mandates->save($other);
            $account = $world->accounts->get(EmployerAccountId::fromString('account-1'));
            $account->markMandateCancelled($now);
            $world->accounts->save($account);
        });

        $view = $this->cancelMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('cancelled', $view->state);
        static::assertSame(['031'], $world->gateway->calls());
        static::assertSame([], $world->publisher->names());
        static::assertSame(['call:031', 'begin', 'rollback', 'begin', 'append:', 'commit'], $world->journal->entries());
    }

    public function testCancellationGivesUpAfterThreeAttemptsWithACallFreeRecovery(): void
    {
        $world = new EmployerWorld();
        $world->registeredMandate($world->trackedAccount());
        $world->gateway->queue('031', null);
        $mandates = new ConcurrentMandates($world->mandates, $world->transactions);
        $mandates->beforeSave(static function () use ($world): void {
            $world->mandates->save($world->mandates->get(MandateId::fromString('mandate-1')));
        }, 99);

        $failure = $this->recordingFailure(
            fn(): MandateView => $this->cancelMandate($world, new ConcurrentAccounts($world->accounts, $world->transactions), $mandates)(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector())),
        );

        static::assertSame('mandate.recording_failed', $failure->violationCode());
        static::assertSame(['operation' => '031', 'attempts' => 3, 'last_code' => 'mandate.concurrent_modification'], $failure->params());
        static::assertSame(['031'], $world->gateway->calls());
        static::assertCount(3, array_keys($world->journal->entries(), 'rollback', true));

        // The call-free recovery records the cancellation: the 031 call is never made again.
        $recorded = (new RecordMandateCancellationHandler($world->locator, $world->mandates, $world->recorder))(
            new RecordMandateCancellation(EmployerWorld::tenant(), EmployerWorld::selector()),
        );
        static::assertSame('cancelled', $recorded->state);
        static::assertSame(['031'], $world->gateway->calls());
    }

    public function testAnAccountConflictIsRetriedWhileCancelling(): void
    {
        $world = new EmployerWorld();
        $world->registeredMandate($world->trackedAccount());
        $world->gateway->queue('031', null);
        $accounts = new ConcurrentAccounts($world->accounts, $world->transactions);
        $accounts->beforeSave(static function (): never {
            throw InvariantViolation::because(EmployerAccountRepository::CONCURRENT_MODIFICATION, 'Conflict.');
        });

        $view = $this->cancelMandate($world, $accounts, new ConcurrentMandates($world->mandates, $world->transactions))(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('cancelled', $view->state);
        static::assertSame(['031'], $world->gateway->calls());
        static::assertSame(EmployerStage::MandateCancelled, $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
    }

    /**
     * Another writer saves the record before each save of the use case: it is "outcome_unknown" the first time, and
     * never settled.
     */
    private static function refreshStatus(EmployerWorld $world): void
    {
        $other = $world->accounts->get(EmployerAccountId::fromString('account-1'));
        $other->applyVerification(EmployerSamples::verification(), Instant::fromIso8601(EmployerWorld::NOW));
        $world->accounts->save($other);
    }

    /**
     * @param callable(): mixed $operation
     * @param-immediately-invoked-callable $operation
     */
    private function recordingFailure(callable $operation): MandateRecordingFailed
    {
        try {
            $operation();
        } catch (MandateRecordingFailed $failure) {
            return $failure;
        }
        static::fail('Expected MandateRecordingFailed.');
    }

    /**
     * @param callable(): mixed $operation
     * @param-immediately-invoked-callable $operation
     */
    private function assertViolation(string $code, callable $operation): void
    {
        try {
            $operation();
        } catch (InvariantViolation $violation) {
            static::assertSame($code, $violation->violationCode());

            return;
        }
        static::fail('Expected the violation ' . $code . '.');
    }

    private function declare(DeclarationWorld $world): DeclarationSubmission
    {
        return (new DeclareSalaryHandler(
            $world->records,
            $world->accounts,
            $world->submitter(),
            $world->guard(),
            $world->ids,
            $world->clock,
            $world->unitOfWork,
            $world->policy,
        ))(new DeclareSalary(
            DeclarationWorld::tenant(),
            ExternalReference::fromString('decl-ref-1'),
            DeclarationWorld::employer(),
            RecordSamples::childminder(),
            false,
        ));
    }

    private function reconcile(DeclarationWorld $world): BatchReport
    {
        return (new ReconcileStaleDeclarationsHandler($world->records, $world->clock, $world->unitOfWork, $world->policy))(
            new ReconcileStaleDeclarations(null),
        );
    }

    private function registerMandate(EmployerWorld $world, ConcurrentAccounts $accounts, ConcurrentMandates $mandates): RegisterMandateHandler
    {
        return new RegisterMandateHandler($world->locator, $world->mandates, $world->gateway, $this->recorder($world, $accounts, $mandates));
    }

    private function importMandate(EmployerWorld $world, ConcurrentAccounts $accounts, ConcurrentMandates $mandates): ImportMandateHandler
    {
        return new ImportMandateHandler($world->locator, $world->mandates, $this->recorder($world, $accounts, $mandates));
    }

    private function cancelMandate(EmployerWorld $world, ConcurrentAccounts $accounts, ConcurrentMandates $mandates): CancelMandateHandler
    {
        return new CancelMandateHandler($world->locator, $world->mandates, $world->gateway, $this->recorder($world, $accounts, $mandates));
    }

    private function recorder(EmployerWorld $world, ConcurrentAccounts $accounts, ConcurrentMandates $mandates): MandateRecorder
    {
        return new MandateRecorder($mandates, $accounts, $world->ids, $world->clock, $world->unitOfWork);
    }
}
