<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

final class RecordingRateLimiter implements RateLimiter
{
    /** @var list<string> */
    private array $acquired = [];

    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $this->acquired[] = $tenantId->value() . ':' . $operation->value;
    }

    /**
     * @return list<string>
     */
    public function acquired(): array
    {
        return $this->acquired;
    }
}
