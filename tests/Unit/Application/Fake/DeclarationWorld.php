<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

/**
 * Wiring of the declaration use cases for the unit tests: in-memory repositories (the declaration one journals), a
 * transactional manager that really rolls back (SnapshottingTransactionManager), a publisher and an outbox that journal what they do, a scripted gateway, a frozen clock and
 * sequential ids. Time: NOW (2026-11-17T09:00:00Z).
 *
 * One property per collaborator and one builder per aggregate, hence the property and method counts.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:too-many-properties
 */
final readonly class DeclarationWorld
{
    public const NOW = '2026-11-17T09:00:00Z';
    public const RECORDED_AT = '2026-11-17T08:00:00Z';
    public const BIRTH_DATE = '1975-02-23';

    public Journal $journal;
    public FixedClock $clock;
    public SequentialIdGenerator $ids;
    public JournalingDeclarationRecords $records;
    public InMemoryEmployerAccountRepository $accounts;
    public InMemoryMandateRepository $mandates;
    public FakeSalaryGateway $gateway;
    public RecordingEventPublisher $publisher;
    public JournalingOutbox $outbox;
    public SnapshottingTransactionManager $transactions;
    public UnitOfWork $unitOfWork;
    public DeclarationLocator $locator;

    /**
     * @param bool $transactional true: a failed unit of work is rolled back (the repositories and the outbox get their
     *                            state back, "rollback" in the journal); false: the unit of work runs on
     *                            ImmediateTransactionManager, like the in-memory persistence of the builder (nothing is
     *                            rolled back, no "begin" nor "commit"; $transactions is then unused)
     */
    public function __construct(public WorkflowPolicy $policy = new WorkflowPolicy(), bool $transactional = true)
    {
        $this->journal = new Journal();
        $this->clock = new FixedClock(self::NOW);
        $this->ids = new SequentialIdGenerator('new-');
        $this->records = new JournalingDeclarationRecords($this->journal);
        $this->accounts = new InMemoryEmployerAccountRepository();
        $this->mandates = new InMemoryMandateRepository();
        $this->gateway = new FakeSalaryGateway($this->journal);
        $this->publisher = new RecordingEventPublisher($this->journal);
        $this->outbox = new JournalingOutbox($this->journal);
        $this->transactions = new SnapshottingTransactionManager($this->journal, $this->records->inner(), $this->accounts, $this->mandates, $this->outbox);
        if ($transactional) {
            $this->records->withTransactions($this->transactions);
        }
        $this->unitOfWork = new UnitOfWork($transactional ? $this->transactions : new ImmediateTransactionManager(), $this->publisher, new RecordingFailureReporter(), $this->outbox);
        $this->locator = new DeclarationLocator($this->records);
    }

    public static function tenant(string $value = 'tenant-1'): TenantId
    {
        return TenantId::fromString($value);
    }

    public static function reference(string $value = 'decl-ref-1'): ExternalReference
    {
        return ExternalReference::fromString($value);
    }

    public static function employer(string $number = 'Y1869005780009'): EmployerReference
    {
        return EmployerReference::of(PajeEmployerNumber::of($number), CalendarDate::fromString(self::BIRTH_DATE));
    }

    public function submitter(): DeclarationSubmitter
    {
        return new DeclarationSubmitter($this->records, $this->gateway, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function guard(): MandateGuard
    {
        return new MandateGuard($this->mandates, $this->policy);
    }

    /**
     * Saves a tracked account of the employer (no event kept) and returns it.
     */
    public function trackedAccount(string $id = 'account-1', string $number = 'Y1869005780009'): EmployerAccount
    {
        $account = EmployerAccount::track(
            EmployerAccountId::fromString($id),
            self::tenant(),
            ExternalReference::fromString('employer-' . $id),
            self::employer($number),
            Instant::fromIso8601(self::RECORDED_AT),
        );
        $account->releaseEvents();
        $this->accounts->save($account);

        return $account;
    }

    /**
     * Saves a registered mandate of the employer and returns it.
     */
    public function registeredMandate(string $number = 'Y1869005780009'): Mandate
    {
        $account = $this->accounts->findByEmployerNumber(self::tenant(), PajeEmployerNumber::of($number)) ?? $this->trackedAccount('account-m', $number);
        $mandate = Mandate::register(
            MandateId::fromString('mandate-1'),
            self::tenant(),
            $account->externalReference(),
            self::employer($number),
            $account->id(),
            Instant::fromIso8601(self::RECORDED_AT),
        );
        $mandate->releaseEvents();
        $this->mandates->save($mandate);

        return $mandate;
    }

    /**
     * Record $id (external reference "decl-ref-1", snapshot of the sample childminder declaration for the employer
     * Y1869005780009, tenant-1) driven to the state with $attempts submission attempts (at least 1 for every state but
     * pending), stored, events released.
     */
    public function record(string $id, DeclarationState $state, int $attempts = 1): DeclarationRecord
    {
        return $this->recordWith($id, $state, $attempts, RecordSamples::childminder(), 'decl-ref-1', 'tenant-1', 'Y1869005780009');
    }

    /**
     * Same as {@see self::record()} with every part of the record given.
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public function recordWith(
        string $id,
        DeclarationState $state,
        int $attempts,
        Declaration $declaration,
        string $reference,
        string $tenant,
        string $employerNumber,
    ): DeclarationRecord {
        $at = Instant::fromIso8601(self::RECORDED_AT);
        $record = DeclarationRecord::record(
            DeclarationRecordId::fromString($id),
            self::tenant($tenant),
            self::reference($reference),
            DeclarationSnapshot::fromDeclaration(PajeEmployerNumber::of($employerNumber), $declaration),
            $at,
            null,
        );
        if ($state !== DeclarationState::Pending) {
            for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
                $record->startSubmission($at);
                if ($attempt < $attempts) {
                    $record->markFailed([], $at);
                    $record->retry($at, max(1, $attempts));
                }
            }
            self::finish($record, $state, $at);
        }
        $record->releaseEvents();
        $this->records->add($record);

        return $record;
    }

    private static function finish(DeclarationRecord $record, DeclarationState $state, Instant $at): void
    {
        if ($state === DeclarationState::Declared) {
            $record->markDeclared(RecordSamples::result(), $at);
        }
        if ($state === DeclarationState::Rejected) {
            $record->markRejected(RecordSamples::errors(), $at);
        }
        if ($state === DeclarationState::AlreadyDeclared) {
            $record->markAlreadyDeclared(RecordSamples::errors(), $at);
        }
        if ($state === DeclarationState::OutcomeUnknown) {
            $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, [], $at);
        }
        if ($state === DeclarationState::Error) {
            $record->markFailed(RecordSamples::errors(), $at);
        }
        if ($state === DeclarationState::InvalidCredentials) {
            $record->markInvalidCredentials($at);
        }
    }
}
