<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Cancels the mandate of a tracked employer with URSSAF (method 031, api-reference §2.12), then locally.
 */
final readonly class CancelMandate
{
    public function __construct(
        public TenantId $tenantId,
        public EmployerSelector $employer,
    ) {}
}
