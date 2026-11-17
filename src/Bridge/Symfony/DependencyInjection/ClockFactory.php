<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Psr\Clock\ClockInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Container factory of the clock of the package: the application "clock" service (PSR-20) when it exists, the UTC
 * system clock otherwise (symfony/clock is not required).
 */
final class ClockFactory
{
    public static function create(?ClockInterface $applicationClock): Clock
    {
        return $applicationClock instanceof ClockInterface ? new SystemClock($applicationClock) : new UtcSystemClock();
    }
}
