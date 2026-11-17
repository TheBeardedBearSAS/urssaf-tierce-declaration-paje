<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;

/**
 * An integration event waiting in the outbox.
 */
final readonly class OutboxEntry
{
    /**
     * @param int $sequence position in the outbox (recording order)
     * @param int $attempts failed dispatch attempts so far
     */
    public function __construct(public int $sequence, public IntegrationEvent $event, public int $attempts) {}
}
