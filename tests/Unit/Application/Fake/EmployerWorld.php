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

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateRecorder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;

/**
 * Wiring of the employer-side use cases for the unit tests: in-memory repositories, a transaction manager that rolls back (SnapshottingTransactionManager, or the immediate one on request), a publisher
 * and an outbox that journal what they do, a frozen clock and sequential ids.
 *
 * @mago-expect lint:too-many-properties
 */
final readonly class EmployerWorld
{
    public const NOW = '2026-11-17T09:00:00Z';
    public const NUMBER = 'Y1869005780009';
    public const OTHER_NUMBER = 'Y1869005780010';
    public const BIRTH_DATE = '1975-02-23';

    public Journal $journal;
    public FixedClock $clock;
    public SequentialIdGenerator $ids;
    public InMemoryEmployerAccountRepository $accounts;
    public InMemoryMandateRepository $mandates;
    public InMemoryDeclarationRecordRepository $records;
    public FakeEmployerGateway $gateway;
    public RecordingEventPublisher $publisher;
    public JournalingOutbox $outbox;
    public ?SnapshottingTransactionManager $transactions;
    public UnitOfWork $unitOfWork;
    public EmployerLocator $locator;
    public EmployerStatusRefresher $refresher;
    public MandateRecorder $recorder;

    /**
     * @param bool $transactional true: a failed unit of work is rolled back (the repositories and the outbox get their
     *                            state back, "rollback" in the journal); false: ImmediateTransactionManager, like the
     *                            in-memory persistence of the builder (nothing is rolled back, no "begin" nor "commit")
     */
    public function __construct(bool $transactional = true)
    {
        $this->journal = new Journal();
        $this->clock = new FixedClock(self::NOW);
        $this->ids = new SequentialIdGenerator('id-');
        $this->accounts = new InMemoryEmployerAccountRepository();
        $this->mandates = new InMemoryMandateRepository();
        $this->records = new InMemoryDeclarationRecordRepository();
        $this->gateway = new FakeEmployerGateway($this->journal);
        $this->publisher = new RecordingEventPublisher($this->journal);
        $this->outbox = new JournalingOutbox($this->journal);
        $this->transactions = $transactional ? $this->snapshotting() : null;
        $this->unitOfWork = new UnitOfWork($this->transactions ?? new ImmediateTransactionManager(), $this->publisher, new IgnoredFailures(), $this->outbox);
        $this->locator = new EmployerLocator($this->accounts);
        $this->recorder = new MandateRecorder($this->mandates, $this->accounts, $this->ids, $this->clock, $this->unitOfWork);
        $this->refresher = new EmployerStatusRefresher($this->gateway, $this->accounts, $this->clock, $this->unitOfWork);
    }

    private function snapshotting(): SnapshottingTransactionManager
    {
        return new SnapshottingTransactionManager($this->journal, $this->accounts, $this->mandates, $this->records, $this->outbox);
    }

    public static function tenant(string $value = 'tenant-1'): TenantId
    {
        return TenantId::fromString($value);
    }

    public static function reference(string $value = 'employer-ref-1'): ExternalReference
    {
        return ExternalReference::fromString($value);
    }

    public static function employer(string $number = self::NUMBER, string $birthDate = self::BIRTH_DATE): EmployerReference
    {
        return EmployerReference::of(PajeEmployerNumber::of($number), CalendarDate::fromString($birthDate));
    }

    public static function selector(string $reference = 'employer-ref-1'): EmployerSelector
    {
        return EmployerSelector::byExternalReference(self::reference($reference));
    }

    /**
     * Saves a tracked account (no event kept) and returns it.
     */
    public function trackedAccount(
        string $id = 'account-1',
        string $reference = 'employer-ref-1',
        string $number = self::NUMBER,
        string $tenant = 'tenant-1',
    ): EmployerAccount {
        $account = EmployerAccount::track(
            EmployerAccountId::fromString($id),
            self::tenant($tenant),
            self::reference($reference),
            self::employer($number),
            Instant::fromIso8601('2026-11-01T08:00:00Z'),
        );
        $account->releaseEvents();
        $this->accounts->save($account);

        return $account;
    }

    /**
     * Saves a registered mandate for the account and moves the account to "mandate_registered".
     */
    public function registeredMandate(EmployerAccount $account, string $id = 'mandate-1'): Mandate
    {
        $mandate = Mandate::register(
            MandateId::fromString($id),
            $account->tenantId(),
            $account->externalReference(),
            $account->employer(),
            $account->id(),
            Instant::fromIso8601('2026-11-02T08:00:00Z'),
        );
        $mandate->releaseEvents();
        $this->mandates->save($mandate);
        $account->markMandateRegistered(Instant::fromIso8601('2026-11-02T08:00:00Z'));
        $account->releaseEvents();
        $this->accounts->save($account);

        return $mandate;
    }

    /**
     * Saves an active mandate that the account stage does not follow (another writer saved the mandate, its account save
     * has not happened or failed): the account keeps its stage.
     */
    public function leftBehindMandate(EmployerAccount $account, string $id = 'mandate-1', MandateOrigin $origin = MandateOrigin::Registered): Mandate
    {
        $factory = $origin === MandateOrigin::Imported ? Mandate::import(...) : Mandate::register(...);
        $mandate = $factory(
            MandateId::fromString($id),
            $account->tenantId(),
            $account->externalReference(),
            $account->employer(),
            $account->id(),
            Instant::fromIso8601('2026-11-02T08:00:00Z'),
        );
        $mandate->releaseEvents();
        $this->mandates->save($mandate);

        return $mandate;
    }

    /**
     * @return list<string>
     */
    public function publishedEvents(): array
    {
        return $this->publisher->names();
    }
}
