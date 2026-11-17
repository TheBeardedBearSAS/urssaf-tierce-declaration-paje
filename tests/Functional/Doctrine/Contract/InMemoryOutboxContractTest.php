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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;

/**
 * Proves the contract itself: the in-memory outbox satisfies the same scenarios as the Doctrine one.
 */
final class InMemoryOutboxContractTest extends TestCase
{
    use OutboxContract;

    protected function createOutbox(): Outbox
    {
        return new InMemoryOutbox();
    }
}
