<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * Mandates in every state, events released.
 */
final class MandateSamples
{
    public static function id(): MandateId
    {
        return MandateId::fromString('mandate-1');
    }

    public static function registeredAt(): Instant
    {
        return Instant::fromIso8601('2026-11-15T10:00:00Z');
    }

    public static function later(): Instant
    {
        return Instant::fromIso8601('2026-11-16T11:30:00Z');
    }

    public static function registered(): Mandate
    {
        $mandate = Mandate::register(
            self::id(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::accountId(),
            self::registeredAt(),
        );
        $mandate->releaseEvents();

        return $mandate;
    }

    public static function inState(MandateState $state): Mandate
    {
        $mandate = self::registered();
        if ($state === MandateState::Cancelled) {
            $mandate->cancel(Instant::fromIso8601('2026-11-15T12:00:00Z'));
        }
        $mandate->releaseEvents();

        return $mandate;
    }
}
