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

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Declares a salary to URSSAF (methods 050 / 051 by declaration variant), once per tenant and external reference
 * (ADR 0003, ADR 0006).
 *
 * "skipMandateCheck" opts out of the local mandate guard for this command (ADR 0009).
 */
final readonly class DeclareSalary
{
    public function __construct(
        public TenantId $tenantId,
        public ExternalReference $externalReference,
        #[SensitiveParameter]
        public EmployerReference $employer,
        #[SensitiveParameter]
        public Declaration $declaration,
        public bool $skipMandateCheck = false,
    ) {}
}
