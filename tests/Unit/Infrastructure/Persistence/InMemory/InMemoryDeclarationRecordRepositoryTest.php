<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;

/**
 * What is specific to the in-memory repository (snapshots, orders on ties); the behaviour shared with the other
 * adapters is in the repository contract. One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class InMemoryDeclarationRecordRepositoryTest extends TestCase
{
    private InMemoryDeclarationRecordRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryDeclarationRecordRepository();
    }

    public function testEveryReadReturnsANewCopy(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Declared);
        $this->repository->save($record);

        $first = $this->repository->get($record->id());

        static::assertNotSame($record, $first);
        static::assertNotSame($first, $this->repository->get($record->id()));
        static::assertNotSame($first, $this->repository->findByExternalReference($record->tenantId(), $record->externalReference()));
        static::assertNotSame($first, $this->repository->findByState($record->tenantId(), DeclarationState::Declared, 1)[0]);
    }

    public function testAChangeIsOnlyVisibleOnceSaved(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Pending);
        $this->repository->save($record);
        $record->startSubmission(ContractSamples::at('2026-11-11T09:00:00Z'));

        static::assertSame(DeclarationState::Pending, $this->repository->get($record->id())->state());

        $this->repository->save($record);

        static::assertSame(DeclarationState::Submitting, $this->repository->get($record->id())->state());
    }

    public function testAllReturnsCopiesInInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::record(2, DeclarationState::Pending));
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));

        $all = $this->repository->all();

        static::assertSame(['record-2', 'record-1'], array_map(static fn(DeclarationRecord $r): string => $r->id()->value(), $all));
        $all[0]->startSubmission(ContractSamples::at('2026-11-11T09:00:00Z'));
        static::assertSame(DeclarationState::Pending, $this->repository->get(DeclarationRecordId::fromString('record-2'))->state());
    }

    public function testTheRecordsUpdatedAtTheSameInstantKeepTheirInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::record(3, DeclarationState::Error));
        $this->repository->save(ContractSamples::record(1, DeclarationState::Error));
        $this->repository->save(ContractSamples::record(2, DeclarationState::Error));

        static::assertSame(
            ['record-3', 'record-1', 'record-2'],
            array_map(
                static fn(DeclarationRecord $r): string => $r->id()->value(),
                $this->repository->findByState(ContractSamples::tenant(), DeclarationState::Error, 10),
            ),
        );
    }

    public function testTheStaleSubmissionsAttemptedAtTheSameInstantKeepTheirInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::record(2, DeclarationState::Submitting));
        $this->repository->save(ContractSamples::record(1, DeclarationState::Submitting));

        static::assertSame(
            ['record-2', 'record-1'],
            array_map(
                static fn(DeclarationRecord $r): string => $r->id()->value(),
                $this->repository->findStaleSubmissions(ContractSamples::at(), 10),
            ),
        );
    }

    public function testTheCountsOfAStateKeepCountingAfterTheRecordOfAnotherTenant(): void
    {
        $this->repository->save(ContractSamples::recordOfReference(1, 2, DeclarationState::Error, 1, ContractSamples::OTHER_TENANT));
        $this->repository->save(ContractSamples::record(1, DeclarationState::Error));
        $this->repository->save(ContractSamples::record(3, DeclarationState::Error));

        static::assertSame(2, $this->repository->countByState(ContractSamples::tenant())['error']);
    }

    public function testADuplicateIsFoundAfterTheRecordOfAnotherTenant(): void
    {
        $this->repository->save(ContractSamples::recordOfReference(1, 2, DeclarationState::Pending, 1, ContractSamples::OTHER_TENANT));
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $duplicate = ContractSamples::recordOfReference(1, 3, DeclarationState::Pending);

        $this->expectException(InvariantViolation::class);
        $this->repository->save($duplicate);
    }

    public function testClaimingARecordRecordsNoEventOnTheStoredCopy(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));

        $this->repository->claimForSubmission(DeclarationRecordId::fromString('record-1'), ContractSamples::at('2026-11-11T09:00:00Z'));

        static::assertSame([], $this->repository->all()[0]->releaseEvents());
    }

    public function testADuplicateOfAnotherTenantIsNotChecked(): void
    {
        $this->repository->save(ContractSamples::recordOfReference(1, 2, DeclarationState::Declared, 1, ContractSamples::OTHER_TENANT));

        $this->repository->save(ContractSamples::record(1, DeclarationState::Declared));

        static::assertCount(2, $this->repository->all());
    }

    public function testTheTenantsWithAStateAreSortedByIdWhateverTheInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Error, 1, 'tenant-c'));
        $this->repository->save(ContractSamples::record(2, DeclarationState::Error, 1, 'tenant-a'));
        $this->repository->save(ContractSamples::record(3, DeclarationState::Error, 1, 'tenant-b'));

        static::assertSame(
            ['tenant-a', 'tenant-b', 'tenant-c'],
            array_map(static fn(TenantId $tenant): string => $tenant->value(), $this->repository->tenantsWithState(DeclarationState::Error)),
        );
    }

    public function testTheCountsOfAStateIgnoreOtherTenants(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Error, 1, ContractSamples::OTHER_TENANT));

        static::assertSame(0, $this->repository->countByState(ContractSamples::tenant())['error']);
        static::assertSame(1, $this->repository->countByState(ContractSamples::tenant(ContractSamples::OTHER_TENANT))['error']);
    }
}
