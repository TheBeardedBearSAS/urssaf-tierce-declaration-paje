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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Estimates the contributions of an employment: a childminder (method 040) or a home care (method 041) request.
 */
final readonly class EstimateContributions
{
    public function __construct(
        public TenantId $tenantId,
        public ChildminderEstimateRequest|HomeCareEstimateRequest $request,
    ) {}
}
