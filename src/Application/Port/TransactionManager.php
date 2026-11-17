<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

use Throwable;

interface TransactionManager
{
    /**
     * Runs the operation in a transaction: committed when it returns, rolled back (and the throwable rethrown
     * unchanged) when it throws.
     *
     * @template T
     *
     * @param callable(): T $operation
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     *
     * @throws Throwable
     */
    public function transactional(callable $operation): mixed;
}
