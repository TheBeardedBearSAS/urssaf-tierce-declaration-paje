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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Reads the 020 snapshot of the least recently checked employers of a tenant, never checked first.
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SyncEmployerStatuses
{
    public function __construct(
        public TenantId $tenantId,
        public ?int $limit,
    ) {}
}
