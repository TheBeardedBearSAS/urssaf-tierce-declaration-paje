<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

/**
 * Result of DeclareSalary: the declaration record as it stands and what the use case did (ADR 0003, ADR 0006).
 */
final readonly class DeclarationSubmission
{
    public function __construct(public DeclarationView $declaration, public ItemOutcome $outcome) {}
}
