<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Mandate;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateTimeline;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

final class MandateTimelineTest extends TestCase
{
    public function testRegistered(): void
    {
        $at = Instant::fromIso8601('2026-11-15T10:00:00Z');

        $timeline = MandateTimeline::registered($at);

        static::assertSame($at, $timeline->registeredAt());
        static::assertNull($timeline->cancelledAt());
        static::assertSame($at, $timeline->updatedAt());
    }

    public function testWithCancellation(): void
    {
        $registered = Instant::fromIso8601('2026-11-15T10:00:00Z');
        $cancelled = Instant::fromIso8601('2026-11-16T10:00:00Z');

        $timeline = MandateTimeline::registered($registered)->withCancellation($cancelled);

        static::assertSame($registered, $timeline->registeredAt());
        static::assertSame($cancelled, $timeline->cancelledAt());
        static::assertSame($cancelled, $timeline->updatedAt());
    }

    public function testEquality(): void
    {
        $registered = Instant::fromIso8601('2026-11-15T10:00:00Z');
        $cancelled = Instant::fromIso8601('2026-11-16T10:00:00Z');
        $timeline = MandateTimeline::registered($registered)->withCancellation($cancelled);

        static::assertTrue($timeline->equals(MandateTimeline::registered($registered)->withCancellation($cancelled)));
        static::assertFalse($timeline->equals(MandateTimeline::registered($registered)));
        static::assertFalse(MandateTimeline::registered($registered)->equals($timeline));
        static::assertFalse(MandateTimeline::registered($registered)->equals(MandateTimeline::registered($cancelled)));
        static::assertFalse($timeline->equals(MandateTimeline::registered($registered)->withCancellation(Instant::fromIso8601('2026-11-17T10:00:00Z'))));
        static::assertFalse($timeline->equals(MandateTimeline::registered(Instant::fromIso8601('2026-11-14T10:00:00Z'))->withCancellation($cancelled)));
    }
}
