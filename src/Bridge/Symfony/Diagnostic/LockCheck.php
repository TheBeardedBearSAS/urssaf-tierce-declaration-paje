<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;

/**
 * Overlapping crons and workers are prevented by a lock.
 */
final readonly class LockCheck implements InstallationCheck
{
    public function __construct(private OperationLocks $locks) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if ($this->locks->isEnabled()) {
            return [new CheckResult('lock', '', CheckStatus::Ok, 'Enabled: use a store shared by every host that runs the crons and workers.')];
        }

        return [new CheckResult('lock', '', CheckStatus::Warning, 'Disabled: overlapping runs are only protected by the claims (install symfony/lock, see docs/symfony.md).')];
    }
}
