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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;

/**
 * Proves the contract itself: the in-memory repository satisfies the same scenarios as the Doctrine one.
 */
final class InMemoryDeclarationRecordRepositoryContractTest extends TestCase
{
    use DeclarationRecordRepositoryContract;

    protected function createRepository(): DeclarationRecordRepository
    {
        return new InMemoryDeclarationRecordRepository();
    }

    protected function transactions(): TransactionManager
    {
        return new ImmediateTransactionManager();
    }

    protected function anotherWriter(): DeclarationRecordRepository
    {
        return $this->repository;
    }
}
