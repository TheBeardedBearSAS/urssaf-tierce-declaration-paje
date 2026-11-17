<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\PersistenceRegistrar;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

/**
 * The persistence drivers: "in_memory" (default) and "custom".
 */
final class PersistenceWiringTest extends TestCase
{
    public function testTheInMemoryDriverRegistersTheInMemoryImplementations(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(InMemoryEmployerAccountRepository::class, $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class));
        static::assertInstanceOf(InMemoryMandateRepository::class, $harness->privateService(MandateRepository::class, MandateRepository::class));
        static::assertInstanceOf(InMemoryDeclarationRecordRepository::class, $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class));
        static::assertInstanceOf(ImmediateTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
        static::assertInstanceOf(InMemoryWorkflowStatistics::class, $harness->privateService(WorkflowStatistics::class, WorkflowStatistics::class));
    }

    public function testTheStatisticsReadTheRepositoriesOfTheApplication(): void
    {
        $harness = Harness::boot();

        Scenario::declaration($harness);

        $statistics = $harness->privateService(WorkflowStatistics::class, WorkflowStatistics::class);
        static::assertNotSame([], $statistics->employerStages());
        static::assertNotSame([], $statistics->mandateStates());
        static::assertNotSame([], $statistics->declarationStates());
    }

    public function testTheCustomDriverUsesTheRepositoriesOfTheApplication(): void
    {
        $harness = Harness::boot(['persistence' => [
            'driver' => 'custom',
            'employer_account_repository' => 'test.accounts',
            'mandate_repository' => 'test.mandates',
            'declaration_record_repository' => 'test.declarations',
        ]]);

        static::assertSame($harness->privateService('test.accounts', EmployerAccountRepository::class), $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class));
        static::assertSame($harness->privateService('test.mandates', MandateRepository::class), $harness->privateService(MandateRepository::class, MandateRepository::class));
        static::assertSame($harness->privateService('test.declarations', DeclarationRecordRepository::class), $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class));
        static::assertInstanceOf(ImmediateTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
        static::assertNotSame($harness->privateService('test.transactions', TransactionManager::class), $harness->privateService(TransactionManager::class, TransactionManager::class));
        static::assertFalse($harness->hasPrivate(WorkflowStatistics::class));
    }

    public function testTheCustomDriverUsesTheTransactionManagerAndTheStatisticsOfTheApplication(): void
    {
        $harness = Harness::boot(['persistence' => [
            'driver' => 'custom',
            'employer_account_repository' => 'test.accounts',
            'mandate_repository' => 'test.mandates',
            'declaration_record_repository' => 'test.declarations',
            'transaction_manager' => 'test.transactions',
            'workflow_statistics' => 'test.statistics',
        ]]);

        static::assertSame($harness->privateService('test.transactions', TransactionManager::class), $harness->privateService(TransactionManager::class, TransactionManager::class));
        static::assertSame($harness->privateService('test.statistics', WorkflowStatistics::class), $harness->privateService(WorkflowStatistics::class, WorkflowStatistics::class));
    }

    public function testTheStatisticsPredicate(): void
    {
        static::assertTrue(PersistenceRegistrar::hasStatistics('in_memory', null));
        static::assertTrue(PersistenceRegistrar::hasStatistics('custom', 'my.statistics'));
        static::assertFalse(PersistenceRegistrar::hasStatistics('custom', null));
    }
}
