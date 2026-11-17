<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared;

use DateTimeImmutable;

/**
 * Marker for every event recorded by an aggregate.
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
