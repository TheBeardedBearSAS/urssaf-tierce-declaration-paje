<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;

/**
 * Transaction port for stores without transactions: runs the operation immediately, without any rollback.
 */
final class ImmediateTransactionManager implements TransactionManager
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        return $operation();
    }
}
