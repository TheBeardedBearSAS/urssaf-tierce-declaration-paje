<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http;

use DateTimeImmutable;

/**
 * Elapsed time helper built on the {@see \TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock} port values.
 *
 * @internal
 */
final class Duration
{
    public static function millisecondsSince(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $microseconds = self::epochMicroseconds($end) - self::epochMicroseconds($start);

        return intdiv($microseconds, 1000);
    }

    private static function epochMicroseconds(DateTimeImmutable $instant): int
    {
        return (int) $instant->format('Uu');
    }
}
