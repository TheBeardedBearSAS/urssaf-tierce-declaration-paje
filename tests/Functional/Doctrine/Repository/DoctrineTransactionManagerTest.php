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
use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\HostNote;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * The transaction manager on the connection of an entity manager: commit, rollback, and the detachment of the package
 * entities after a rollback.
 */
final class DoctrineTransactionManagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineEmployerAccountRepository $repository;

    private DoctrineTransactionManager $transactions;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->repository = new DoctrineEmployerAccountRepository($this->entityManager);
        $this->transactions = new DoctrineTransactionManager($this->entityManager);
    }

    public function testItCommitsAndReturnsTheResultOfTheOperation(): void
    {
        $connection = $this->entityManager->getConnection();

        $result = $this->transactions->transactional(function () use ($connection): string {
            $this->repository->save(ContractSamples::pendingAccount(1));

            return 'done:' . ($connection->isTransactionActive() ? 'in' : 'out');
        });

        static::assertSame('done:in', $result);
        static::assertFalse($connection->isTransactionActive());
        $this->entityManager->clear();
        static::assertSame('account-1', $this->repository->get(EmployerAccountId::fromString('account-1'))->id()->value());
    }

    public function testItRethrowsTheSameThrowable(): void
    {
        $failure = new LogicException('boom');

        $this->expectExceptionObject($failure);
        $this->transactions->transactional(static function () use ($failure): never {
            throw $failure;
        });
    }

    public function testItRollsBackAndDetachesThePackageEntitiesWhenTheOperationFails(): void
    {
        $account = ContractSamples::pendingAccount(1);

        try {
            $this->transactions->transactional(function () use ($account): never {
                $this->repository->save($account);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        static::assertTrue($this->entityManager->isOpen());
        static::assertFalse($this->entityManager->contains($account));
        $this->expectException(RecordNotFound::class);
        $this->repository->get(EmployerAccountId::fromString('account-1'));
    }

    public function testItKeepsTheEntitiesOfTheHostApplicationManagedWhenTheOperationFails(): void
    {
        $entityManager = TestDatabase::sharedEntityManager();
        $repository = new DoctrineEmployerAccountRepository($entityManager);
        $transactions = new DoctrineTransactionManager($entityManager);
        $note = new HostNote('flushed before');
        $entityManager->persist($note);
        $entityManager->flush();
        $account = ContractSamples::pendingAccount(1);
        $note->rewrite('changed, not flushed yet');

        try {
            $transactions->transactional(static function () use ($repository, $account): never {
                $repository->save($account);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertFalse($entityManager->contains($account));
        static::assertTrue($entityManager->contains($note));
        static::assertSame($note, $entityManager->find(HostNote::class, $note->id()));
        static::assertSame('changed, not flushed yet', $note->text());
    }

    public function testItDetachesTheThreeAggregatesOfThePackage(): void
    {
        $account = ContractSamples::pendingAccount(1);
        $mandate = ContractSamples::pendingMandate(1);
        $record = ContractSamples::pendingRecord(1);
        $mandates = new DoctrineMandateRepository($this->entityManager);
        $records = new DoctrineDeclarationRecordRepository($this->entityManager);

        try {
            $this->transactions->transactional(function () use ($account, $mandate, $record, $mandates, $records): never {
                $this->repository->save($account);
                $mandates->save($mandate);
                $records->save($record);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertFalse($this->entityManager->contains($account));
        static::assertFalse($this->entityManager->contains($mandate));
        static::assertFalse($this->entityManager->contains($record));
        $managed = 0;
        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
            $managed += \count($entities);
        }
        static::assertSame(0, $managed);
        static::assertSame(0, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_paje_active_mandates'));
    }

    public function testNestedOperationsJoinTheOutermostTransaction(): void
    {
        try {
            $this->transactions->transactional(function (): never {
                $this->transactions->transactional(function (): void {
                    $this->repository->save(ContractSamples::pendingAccount(1));
                });

                throw new LogicException('outer failure');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        $this->expectException(RecordNotFound::class);
        $this->repository->get(EmployerAccountId::fromString('account-1'));
    }

    public function testASavedAggregateRolledBackIsSavedAgainAsANewOne(): void
    {
        $account = ContractSamples::pendingAccount(1);
        try {
            $this->transactions->transactional(function () use ($account): never {
                $this->repository->save($account);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        $this->repository->save($account);

        static::assertSame($account, $this->repository->get(EmployerAccountId::fromString('account-1')));
        static::assertSame([1], $this->entityManager->getConnection()->fetchFirstColumn('SELECT version FROM urssaf_paje_employer_accounts'));
    }
}
