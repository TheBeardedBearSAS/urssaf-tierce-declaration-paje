<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\DeclarationRecordRepositoryContract;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\BeforeNextDelete;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\BeforeNextFlush;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\FileDatabase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * The shared contract and the Doctrine-specific scenarios of the repository, on an in-memory SQLite database. The other
 * writer is a second entity manager over the same connection. One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class DoctrineDeclarationRecordRepositoryTest extends TestCase
{
    use DeclarationRecordRepositoryContract {
        setUp as private contractSetUp;
    }

    private EntityManagerInterface $entityManager;

    private ?DeclarationRecordRepository $other = null;

    private ?FileDatabase $file = null;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->contractSetUp();
    }

    protected function createRepository(): DeclarationRecordRepository
    {
        return new DoctrineDeclarationRecordRepository($this->entityManager);
    }

    protected function transactions(): TransactionManager
    {
        return new DoctrineTransactionManager($this->entityManager);
    }

    protected function anotherWriter(): DeclarationRecordRepository
    {
        return $this->other ??= new DoctrineDeclarationRecordRepository(TestDatabase::anotherEntityManager($this->entityManager));
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }

    /**
     * @phpstan-impure
     *
     * @return list<mixed>
     */
    private function versions(): array
    {
        return $this->entityManager->getConnection()->fetchFirstColumn('SELECT version FROM urssaf_paje_declaration_records');
    }

    /**
     * @param list<DeclarationRecord> $records
     *
     * @return list<string>
     */
    private static function ids(array $records): array
    {
        return array_map(static fn(DeclarationRecord $record): string => $record->id()->value(), $records);
    }

    public function testItReturnsTheManagedInstanceWhileItIsCurrent(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Pending);
        $this->repository->save($record);
        $id = DeclarationRecordId::fromString('record-1');

        static::assertSame($record, $this->repository->get($id));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($id);

        static::assertNotSame($record, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        static::assertSame($reloaded, $this->repository->get($id));
    }

    public function testAnInstanceThatIsNotManagedWhileItsIdentifierIsStoredIsRefusedAndNothingIsOverwritten(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $this->entityManager->clear();

        try {
            $this->repository->save(ContractSamples::record(1, DeclarationState::Declared));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.concurrent_modification', $exception->violationCode());
        }

        static::assertTrue($this->entityManager->isOpen());
        static::assertSame(DeclarationState::Pending, $this->repository->get(DeclarationRecordId::fromString('record-1'))->state());
    }

    public function testAStaleCopyIsRefusedBeforeAnyFlushAndForgotten(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $id = DeclarationRecordId::fromString('record-1');
        $stale = $this->repository->get($id);
        static::assertTrue($this->anotherWriter()->claimForSubmission($id, ContractSamples::at('2026-11-12T10:00:00Z')));
        $stale->startSubmission(ContractSamples::at('2026-11-12T10:00:01Z'));
        $flushed = false;
        BeforeNextFlush::run($this->entityManager, static function () use (&$flushed): void {
            $flushed = true;
        });

        try {
            $this->repository->save($stale);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.concurrent_modification', $exception->violationCode());
            static::assertSame([], $exception->params());
        }

        static::assertFalse($flushed);
        static::assertTrue($this->entityManager->isOpen());
        static::assertFalse($this->entityManager->contains($stale));
    }

    public function testAReadReplacesAManagedCopyThatAnotherWriterClaimed(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $id = DeclarationRecordId::fromString('record-1');
        $managed = $this->repository->get($id);
        static::assertTrue($this->anotherWriter()->claimForSubmission($id, ContractSamples::at('2026-11-12T10:00:00Z')));

        $fresh = $this->repository->get($id);

        static::assertNotSame($managed, $fresh);
        static::assertSame(DeclarationState::Submitting, $fresh->state());
    }

    public function testTheClaimBumpsTheVersionAndForgetsTheManagedCopy(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Pending);
        $this->repository->save($record);
        $id = DeclarationRecordId::fromString('record-1');
        static::assertSame([1], $this->versions());

        static::assertTrue($this->repository->claimForSubmission($id, ContractSamples::at('2026-11-12T10:00:00Z')));
        static::assertFalse($this->repository->claimForSubmission($id, ContractSamples::at('2026-11-12T10:00:00Z')));

        static::assertSame([2], $this->versions());
        static::assertFalse($this->entityManager->contains($record));
        static::assertNotSame($record, $this->repository->get($id));
    }

    public function testAFailedClaimLeavesTheManagedCopyAlone(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Declared);
        $this->repository->save($record);

        static::assertFalse($this->repository->claimForSubmission($record->id(), ContractSamples::at('2026-11-12T10:00:00Z')));

        static::assertTrue($this->entityManager->contains($record));
    }

    public function testTheRecordsDeletedByEmployerAreForgottenByTheEntityManager(): void
    {
        $deleted = ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared);
        $kept = ContractSamples::recordOfEmployer(1, 2, DeclarationState::Error);
        $this->repository->save($deleted);
        $this->repository->save($kept);

        $this->repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1), [DeclarationState::Declared]);

        static::assertFalse($this->entityManager->contains($deleted));
        static::assertTrue($this->entityManager->contains($kept));
    }

    public function testTheClaimClearsTheReasonOfAnUnknownOutcome(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $this->entityManager->getConnection()->executeStatement("UPDATE urssaf_paje_declaration_records SET outcome_unknown_reason = 'stale_submission'");
        $this->entityManager->clear();
        $id = DeclarationRecordId::fromString('record-1');
        static::assertNotNull($this->repository->get($id)->outcomeUnknownReason());
        $this->entityManager->clear();

        static::assertTrue($this->repository->claimForSubmission($id, ContractSamples::at('2026-11-12T10:00:00Z')));

        static::assertNull($this->repository->get($id)->outcomeUnknownReason());
    }

    public function testTheRecordsOfAStateAreListedByUpdateThenCreationThenIdentifierWhateverTheInsertionOrder(): void
    {
        foreach ([
            ContractSamples::record(5, DeclarationState::Pending, 1, ContractSamples::TENANT, '2026-11-10T09:00:00Z'),
            ContractSamples::record(3, DeclarationState::Pending, 1, ContractSamples::TENANT, '2026-11-10T09:00:00Z'),
            ContractSamples::record(4, DeclarationState::Pending, 1, ContractSamples::TENANT, '2026-11-10T09:00:00Z'),
            ContractSamples::record(9, DeclarationState::Pending, 1, ContractSamples::TENANT, '2026-11-09T09:00:00Z'),
        ] as $record) {
            $this->repository->save($record);
        }
        // Same update instant as record-3 and record-4, created later.
        $this->entityManager->getConnection()->executeStatement("UPDATE urssaf_paje_declaration_records SET created_at = '2026-11-11 09:00:00' WHERE id = 'record-4'");
        $this->entityManager->clear();

        static::assertSame(
            ['record-9', 'record-3', 'record-5', 'record-4'],
            self::ids($this->repository->findByState(ContractSamples::tenant(), DeclarationState::Pending, 10)),
        );
    }

    public function testTheStaleSubmissionsAreListedByAttemptThenCreationThenIdentifierWhateverTheInsertionOrder(): void
    {
        foreach ([3, 1, 2] as $number) {
            $this->repository->save(ContractSamples::record($number, DeclarationState::Submitting, 1, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));
        }
        $this->repository->save(ContractSamples::record(9, DeclarationState::Submitting, 1, ContractSamples::OTHER_TENANT, '2026-11-09T09:00:00Z'));

        static::assertSame(
            ['record-9', 'record-1', 'record-2', 'record-3'],
            self::ids($this->repository->findStaleSubmissions(ContractSamples::at('2026-12-01T00:00:00Z'), 10)),
        );
    }

    public function testTheRecordsDeletedByEmployerAreReturnedByCreationThenIdentifierWhateverTheInsertionOrder(): void
    {
        foreach ([3, 1, 2] as $number) {
            $this->repository->save(ContractSamples::recordOfEmployer(1, $number, DeclarationState::Declared, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));
        }
        $this->repository->save(ContractSamples::recordOfEmployer(1, 9, DeclarationState::Declared, ContractSamples::TENANT, '2026-11-01T09:00:00Z'));

        $ids = $this->repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1), [DeclarationState::Declared]);

        static::assertSame(['record-9', 'record-1', 'record-2', 'record-3'], array_map(static fn(DeclarationRecordId $id): string => $id->value(), $ids));
        static::assertSame([], $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_paje_declaration_records'));
    }

    public function testARecordClaimedBetweenTheSelectionAndTheErasureSurvives(): void
    {
        $file = new FileDatabase();
        $this->file = $file;
        $claimed = DeclarationRecordId::fromString('record-1');
        $claimer = new DoctrineDeclarationRecordRepository($file->entityManager());
        $entityManager = $file->entityManagerWith(
            [new BeforeNextDelete(static function () use ($claimer, $claimed): void {
                $claimer->claimForSubmission($claimed, ContractSamples::at('2026-11-12T10:00:00Z'));
            })],
        );
        $repository = new DoctrineDeclarationRecordRepository($entityManager);
        $repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $repository->save(ContractSamples::record(2, DeclarationState::Pending));

        $repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1), [DeclarationState::Pending]);

        static::assertSame(['record-1'], $entityManager->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_paje_declaration_records'));
        static::assertSame(1, $repository->countByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1))['submitting']);
    }

    public function testAConcurrentRecordOfTheSameExternalReferenceIsReportedAsAViolationAndForgotten(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineDeclarationRecordRepository($entityManager);
        $rival = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingRecordOfReference(1, 2));
        });
        $record = ContractSamples::pendingRecordOfReference(1, 1);

        try {
            $repository->save($record);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.duplicate_external_reference', $exception->violationCode());
            static::assertSame(['external_reference' => 'declaration-1'], $exception->params());
        }

        static::assertFalse($entityManager->contains($record));
        static::assertSame(
            ['record-2'],
            $this->file->entityManager()->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_paje_declaration_records'),
        );
    }
}
