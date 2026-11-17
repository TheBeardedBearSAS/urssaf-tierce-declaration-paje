<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Clock;

use DateInterval;
use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;

/**
 * Clock port returning a fixed instant, for deterministic tests and replays. Immutable: advancing returns a new clock.
 */
final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(DateInterval $interval): self
    {
        return new self($this->now->add($interval));
    }
}
