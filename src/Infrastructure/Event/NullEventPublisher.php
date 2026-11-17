<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

/**
 * Event port discarding every event (no event bus configured).
 */
final class NullEventPublisher implements EventPublisher
{
    public function publish(DomainEvent ...$events): void {}
}
