<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;

/**
 * The order and the exact shape of the counts of the in-memory statistics (the contract only compares them sorted).
 */
final class InMemoryWorkflowStatisticsTest extends TestCase
{
    public function testTheCountsKeepTheOrderOfFirstAppearancePerTenantAndState(): void
    {
        $accounts = new InMemoryEmployerAccountRepository();
        $mandates = new InMemoryMandateRepository();
        $declarations = new InMemoryDeclarationRecordRepository();
        $accounts->save(ContractSamples::account(1, EmployerStage::Tracked, 'tenant-b'));
        $accounts->save(ContractSamples::account(2, EmployerStage::Tracked, 'tenant-a'));
        $accounts->save(ContractSamples::account(3, EmployerStage::MandateRegistered, 'tenant-b'));
        $accounts->save(ContractSamples::account(4, EmployerStage::Tracked, 'tenant-b'));
        $mandates->save(ContractSamples::mandate(1, MandateState::Registered));
        $declarations->save(ContractSamples::record(1, DeclarationState::Pending));
        $statistics = new InMemoryWorkflowStatistics($accounts, $mandates, $declarations);

        static::assertSame(
            [['tenant-b', 'tracked', 2], ['tenant-b', 'mandate_registered', 1], ['tenant-a', 'tracked', 1]],
            self::flat($statistics->employerStages()),
        );
        static::assertSame([['tenant-1', 'registered', 1]], self::flat($statistics->mandateStates()));
        static::assertSame([['tenant-1', 'pending', 1]], self::flat($statistics->declarationStates()));
    }

    /**
     * @param list<StateCount> $counts
     *
     * @return list<array{string, string, int}>
     */
    private static function flat(array $counts): array
    {
        return array_map(
            static fn(StateCount $count): array => [$count->tenantId->value(), $count->state, $count->count],
            $counts,
        );
    }
}
