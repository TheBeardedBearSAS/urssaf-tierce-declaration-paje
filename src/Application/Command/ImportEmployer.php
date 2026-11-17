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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Tracks an employer that already exists at Pajemploi, without any URSSAF call (ADR 0006): a wrong birth date is found
 * by the next status synchronisation.
 */
final readonly class ImportEmployer
{
    public function __construct(
        public TenantId $tenantId,
        public ExternalReference $externalReference,
        #[SensitiveParameter]
        public EmployerReference $employer,
    ) {}
}
