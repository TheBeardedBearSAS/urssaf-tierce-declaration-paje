<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;

/**
 * Behaviour shared by every WorkflowStatistics adapter: counts by tenant and state, the pairs without record omitted.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 */
trait WorkflowStatisticsContract
{
    abstract protected function createStatistics(): WorkflowStatistics;

    /**
     * Stores the aggregates (employer accounts, mandates, declaration records) where the statistics count them.
     */
    abstract protected function store(object ...$aggregates): void;

    public function testNothingIsCountedWithoutRecords(): void
    {
        $statistics = $this->createStatistics();

        static::assertSame([], $statistics->employerStages());
        static::assertSame([], $statistics->mandateStates());
        static::assertSame([], $statistics->declarationStates());
    }

    public function testTheEmployerAccountsAreCountedByTenantAndStage(): void
    {
        $statistics = $this->createStatistics();
        $this->store(
            ContractSamples::account(1),
            ContractSamples::account(2),
            ContractSamples::account(3, EmployerStage::MandateRegistered),
            ContractSamples::account(4, EmployerStage::Tracked, ContractSamples::OTHER_TENANT),
        );

        static::assertSame(
            ['tenant-1/mandate_registered=1', 'tenant-1/tracked=2', 'tenant-2/tracked=1'],
            self::normalized($statistics->employerStages()),
        );
        static::assertSame([], $statistics->mandateStates());
        static::assertSame([], $statistics->declarationStates());
    }

    public function testTheMandatesAreCountedByTenantAndState(): void
    {
        $statistics = $this->createStatistics();
        $this->store(
            ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled, ContractSamples::TENANT, ContractSamples::AT),
            ContractSamples::mandateOfEmployer(1, 2, MandateState::Cancelled, ContractSamples::TENANT, ContractSamples::AT),
            ContractSamples::mandateOfEmployer(1, 3, MandateState::Registered, ContractSamples::TENANT, ContractSamples::AT),
            ContractSamples::mandateOfEmployer(1, 4, MandateState::Registered, ContractSamples::OTHER_TENANT, ContractSamples::AT),
        );

        static::assertSame(
            ['tenant-1/cancelled=2', 'tenant-1/registered=1', 'tenant-2/registered=1'],
            self::normalized($statistics->mandateStates()),
        );
        static::assertSame([], $statistics->employerStages());
    }

    public function testTheDeclarationsAreCountedByTenantAndState(): void
    {
        $statistics = $this->createStatistics();
        $this->store(
            ContractSamples::record(1, DeclarationState::Pending),
            ContractSamples::record(2, DeclarationState::Declared),
            ContractSamples::record(3, DeclarationState::Declared),
            ContractSamples::record(4, DeclarationState::OutcomeUnknown, 1, ContractSamples::OTHER_TENANT),
        );

        static::assertSame(
            ['tenant-1/declared=2', 'tenant-1/pending=1', 'tenant-2/outcome_unknown=1'],
            self::normalized($statistics->declarationStates()),
        );
        static::assertSame([], $statistics->employerStages());
    }

    /**
     * @param list<StateCount> $counts
     *
     * @return list<string> "tenant/state=count", sorted (the adapters do not guarantee an order)
     */
    private static function normalized(array $counts): array
    {
        $normalized = array_map(
            static fn(StateCount $count): string => \sprintf('%s/%s=%d', $count->tenantId->value(), $count->state, $count->count),
            $counts,
        );
        sort($normalized);

        return $normalized;
    }
}
