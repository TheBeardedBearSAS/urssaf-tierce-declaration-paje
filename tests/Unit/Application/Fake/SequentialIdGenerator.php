<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;

final class SequentialIdGenerator implements IdGenerator
{
    private int $next = 1;

    /**
     * @param non-empty-string $prefix
     */
    public function __construct(private readonly string $prefix = 'id-') {}

    public function generate(): string
    {
        return $this->prefix . $this->next++;
    }
}
