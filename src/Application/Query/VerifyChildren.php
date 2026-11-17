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

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Verifies the entitlement of children of an employer (method 022).
 */
final readonly class VerifyChildren
{
    /**
     * @param list<Child> $children
     */
    public function __construct(
        public TenantId $tenantId,
        #[SensitiveParameter]
        public EmployerReference $employer,
        #[SensitiveParameter]
        public array $children,
    ) {}
}
