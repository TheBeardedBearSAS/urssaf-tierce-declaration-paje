<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\WorkflowStatisticsContract;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * The shared statistics contract on an in-memory SQLite database, the aggregates stored by the Doctrine repositories.
 */
final class DoctrineWorkflowStatisticsContractTest extends TestCase
{
    use WorkflowStatisticsContract;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
    }

    protected function createStatistics(): WorkflowStatistics
    {
        return new DoctrineWorkflowStatistics($this->entityManager);
    }

    protected function store(object ...$aggregates): void
    {
        foreach ($aggregates as $aggregate) {
            match (true) {
                $aggregate instanceof EmployerAccount => (new DoctrineEmployerAccountRepository($this->entityManager))->save($aggregate),
                $aggregate instanceof Mandate => (new DoctrineMandateRepository($this->entityManager))->save($aggregate),
                $aggregate instanceof DeclarationRecord => (new DoctrineDeclarationRecordRepository($this->entityManager))->save($aggregate),
                default => self::fail('Unsupported aggregate ' . $aggregate::class . '.'),
            };
        }
    }

    public function testTheCountsAreOrderedByTenantThenStateWhateverTheInsertionOrder(): void
    {
        $this->store(
            ContractSamples::account(1, EmployerStage::MandateRegistered, ContractSamples::OTHER_TENANT),
            ContractSamples::account(2, EmployerStage::Tracked),
            ContractSamples::account(3, EmployerStage::MandateRegistered),
            ContractSamples::mandateOfEmployer(1, 1, MandateState::Registered, ContractSamples::OTHER_TENANT),
            ContractSamples::mandateOfEmployer(2, 2, MandateState::Registered),
            ContractSamples::mandateOfEmployer(3, 3, MandateState::Cancelled),
            ContractSamples::record(1, DeclarationState::Pending, 1, ContractSamples::OTHER_TENANT),
            ContractSamples::record(2, DeclarationState::Declared),
            ContractSamples::record(3, DeclarationState::Declared),
            ContractSamples::record(4, DeclarationState::Pending),
        );
        $statistics = $this->createStatistics();

        static::assertSame(['tenant-1/mandate_registered/1', 'tenant-1/tracked/1', 'tenant-2/mandate_registered/1'], self::listed($statistics->employerStages()));
        static::assertSame(['tenant-1/cancelled/1', 'tenant-1/registered/1', 'tenant-2/registered/1'], self::listed($statistics->mandateStates()));
        static::assertSame(['tenant-1/declared/2', 'tenant-1/pending/1', 'tenant-2/pending/1'], self::listed($statistics->declarationStates()));
    }

    /**
     * @param list<StateCount> $counts
     *
     * @return list<string>
     */
    private static function listed(array $counts): array
    {
        return array_map(static fn(StateCount $count): string => \sprintf('%s/%s/%d', $count->tenantId->value(), $count->state, $count->count), $counts);
    }
}
