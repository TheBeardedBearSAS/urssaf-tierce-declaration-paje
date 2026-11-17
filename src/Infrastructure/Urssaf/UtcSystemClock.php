<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf;

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;

/**
 * Default clock of {@see GatewayOptions}: the current system time, in UTC, without any PSR-20 implementation.
 *
 * @internal
 */
final readonly class UtcSystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
