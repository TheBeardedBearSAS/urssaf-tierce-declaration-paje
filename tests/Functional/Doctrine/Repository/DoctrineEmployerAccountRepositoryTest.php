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
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\EmployerAccountRepositoryContract;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\BeforeNextFlush;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\FileDatabase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * The shared contract and the Doctrine-specific scenarios of the repository, on an in-memory SQLite database. The other
 * writer is a second entity manager over the same connection. One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class DoctrineEmployerAccountRepositoryTest extends TestCase
{
    use EmployerAccountRepositoryContract {
        setUp as private contractSetUp;
    }

    private EntityManagerInterface $entityManager;

    private ?EmployerAccountRepository $other = null;

    private ?FileDatabase $file = null;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->contractSetUp();
    }

    protected function createRepository(): EmployerAccountRepository
    {
        return new DoctrineEmployerAccountRepository($this->entityManager);
    }

    protected function anotherWriter(): EmployerAccountRepository
    {
        return $this->other ??= new DoctrineEmployerAccountRepository(TestDatabase::anotherEntityManager($this->entityManager));
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }

    /**
     * @param list<EmployerAccount> $accounts
     *
     * @return list<string>
     */
    private static function ids(array $accounts): array
    {
        return array_map(static fn(EmployerAccount $account): string => $account->id()->value(), $accounts);
    }

    public function testItReturnsTheManagedInstanceWhileItIsCurrent(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);
        $id = EmployerAccountId::fromString('account-1');

        static::assertSame($account, $this->repository->get($id));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($id);

        static::assertNotSame($account, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        static::assertSame($reloaded, $this->repository->get($id));
    }

    public function testAnInstanceThatIsNotManagedWhileItsIdentifierIsStoredIsRefusedAndNothingIsOverwritten(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $this->entityManager->clear();

        try {
            $this->repository->save(ContractSamples::account(1, EmployerStage::MandateRegistered));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.concurrent_modification', $exception->violationCode());
        }

        static::assertTrue($this->entityManager->isOpen());
        static::assertSame(EmployerStage::Tracked, $this->repository->get(EmployerAccountId::fromString('account-1'))->stage());
    }

    public function testAStaleCopyIsRefusedBeforeAnyFlushAndForgotten(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $id = EmployerAccountId::fromString('account-1');
        $stale = $this->repository->get($id);
        $other = $this->anotherWriter()->get($id);
        $other->markMandateRegistered(ContractSamples::at('2026-11-12T09:00:00Z'));
        $this->anotherWriter()->save($other);
        $stale->markMandateRegistered(ContractSamples::at('2026-11-13T09:00:00Z'));
        $flushed = false;
        BeforeNextFlush::run($this->entityManager, static function () use (&$flushed): void {
            $flushed = true;
        });

        try {
            $this->repository->save($stale);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.concurrent_modification', $exception->violationCode());
            static::assertSame([], $exception->params());
        }

        static::assertFalse($flushed);
        static::assertTrue($this->entityManager->isOpen());
        static::assertFalse($this->entityManager->contains($stale));
        static::assertSame('2026-11-12T09:00:00Z', $this->repository->get($id)->updatedAt()->toIso8601());
    }

    public function testAReadReplacesAManagedCopyThatAnotherWriterChanged(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $id = EmployerAccountId::fromString('account-1');
        $managed = $this->repository->get($id);
        $other = $this->anotherWriter()->get($id);
        $other->markMandateRegistered(ContractSamples::at('2026-11-12T09:00:00Z'));
        $this->anotherWriter()->save($other);

        $fresh = $this->repository->get($id);

        static::assertNotSame($managed, $fresh);
        static::assertSame(EmployerStage::MandateRegistered, $fresh->stage());
    }

    public function testTheNeverCheckedAccountsComeFirstThenTheOldestCheckWithCreationAndIdentifierAsTieBreakers(): void
    {
        foreach ([
            ContractSamples::checkedAccount(5, '2026-11-12T09:00:00Z', ContractSamples::TENANT, '2026-11-01T09:00:00Z'),
            ContractSamples::checkedAccount(4, '2026-11-11T09:00:00Z', ContractSamples::TENANT, '2026-11-02T09:00:00Z'),
            ContractSamples::checkedAccount(3, '2026-11-11T09:00:00Z', ContractSamples::TENANT, '2026-11-01T09:00:00Z'),
            ContractSamples::pendingAccount(8, ContractSamples::TENANT, '2026-11-03T09:00:00Z'),
            ContractSamples::pendingAccount(7, ContractSamples::TENANT, '2026-11-02T09:00:00Z'),
            ContractSamples::pendingAccount(2, ContractSamples::TENANT, '2026-11-02T09:00:00Z'),
            ContractSamples::pendingAccount(9, ContractSamples::OTHER_TENANT, '2026-11-01T09:00:00Z'),
        ] as $account) {
            $this->repository->save($account);
        }

        static::assertSame(
            ['account-2', 'account-7', 'account-8', 'account-3', 'account-4', 'account-5'],
            self::ids($this->repository->findLeastRecentlyChecked(ContractSamples::tenant(), 10)),
        );
        static::assertSame(['account-2', 'account-7'], self::ids($this->repository->findLeastRecentlyChecked(ContractSamples::tenant(), 2)));
    }

    public function testAConcurrentAccountOfTheSameEmployerNumberIsReportedAsAViolationAndForgotten(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineEmployerAccountRepository($entityManager);
        $rival = new DoctrineEmployerAccountRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingAccountOfEmployer(1, 2));
        });
        $account = ContractSamples::pendingAccountOfEmployer(1, 1);

        try {
            $repository->save($account);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_employer_number', $exception->violationCode());
            static::assertSame([], $exception->params());
        }

        static::assertFalse($entityManager->contains($account));
        static::assertSame(
            ['account-2'],
            $this->file->entityManager()->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_paje_employer_accounts'),
        );
    }

    public function testAConcurrentAccountOfTheSameExternalReferenceIsReportedAsAViolation(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineEmployerAccountRepository($entityManager);
        $rival = new DoctrineEmployerAccountRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingAccountOfEmployer(1, 2));
        });
        // Same external reference ("employer-1"), another employer number: only that constraint is broken.
        $account = EmployerAccount::track(
            EmployerAccountId::fromString('account-1'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-1'),
            ContractSamples::employer(2),
            ContractSamples::at(),
        );

        try {
            $repository->save($account);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_external_reference', $exception->violationCode());
            static::assertSame(['external_reference' => 'employer-1'], $exception->params());
        }
    }
}
