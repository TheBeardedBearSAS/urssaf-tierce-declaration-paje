<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\FailureReporter;
use Throwable;

/**
 * Default {@see FailureReporter} of the {@see UnitOfWork}: the failures are dropped. Replace it with one that logs
 * (the framework bridges provide a PSR-3 one).
 */
final readonly class IgnoredFailures implements FailureReporter
{
    #[Override]
    public function eventPublicationFailed(Throwable $failure, array $events): void {}
}
