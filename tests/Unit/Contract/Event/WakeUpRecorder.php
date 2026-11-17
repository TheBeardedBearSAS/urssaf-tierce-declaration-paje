<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Contract\Event;

/**
 * Records whether an instance was ever unserialized (its __wakeup() ran).
 */
final class WakeUpRecorder
{
    private static bool $wokenUp = false;

    public function __wakeup(): void
    {
        self::$wokenUp = true;
    }

    public static function wokenUp(): bool
    {
        return self::$wokenUp;
    }
}
