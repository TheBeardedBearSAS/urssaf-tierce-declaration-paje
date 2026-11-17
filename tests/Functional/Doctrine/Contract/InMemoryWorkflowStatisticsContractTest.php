<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;

/**
 * Proves the contract itself: the in-memory statistics satisfy the same scenarios as the Doctrine ones.
 */
final class InMemoryWorkflowStatisticsContractTest extends TestCase
{
    use WorkflowStatisticsContract;

    private InMemoryEmployerAccountRepository $accounts;

    private InMemoryMandateRepository $mandates;

    private InMemoryDeclarationRecordRepository $declarations;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryEmployerAccountRepository();
        $this->mandates = new InMemoryMandateRepository();
        $this->declarations = new InMemoryDeclarationRecordRepository();
    }

    protected function createStatistics(): WorkflowStatistics
    {
        return new InMemoryWorkflowStatistics($this->accounts, $this->mandates, $this->declarations);
    }

    protected function store(object ...$aggregates): void
    {
        foreach ($aggregates as $aggregate) {
            match (true) {
                $aggregate instanceof EmployerAccount => $this->accounts->save($aggregate),
                $aggregate instanceof Mandate => $this->mandates->save($aggregate),
                $aggregate instanceof DeclarationRecord => $this->declarations->save($aggregate),
                default => self::fail('Unsupported aggregate ' . $aggregate::class . '.'),
            };
        }
    }
}
