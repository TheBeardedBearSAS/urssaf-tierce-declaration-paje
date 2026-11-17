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
use Throwable;

/**
 * Where the outbox relay delivers the integration events (a message bus, an event dispatcher...).
 */
interface IntegrationEventSink
{
    /**
     * @throws Throwable when the event could not be delivered: it stays in the outbox and is relayed again later
     */
    public function dispatch(IntegrationEvent $event): void;
}
