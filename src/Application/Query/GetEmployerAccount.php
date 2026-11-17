<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Reads a tracked employer account with its mandate, from the local store (no URSSAF call).
 */
final readonly class GetEmployerAccount
{
    public function __construct(
        public TenantId $tenantId,
        public EmployerSelector $employer,
    ) {}
}
