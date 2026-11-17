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

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;

/**
 * Delivers the integration events to the listeners of a PSR-14 dispatcher (in the relay process): a listener that
 * throws keeps the event in the outbox.
 */
final readonly class Psr14IntegrationEventSink implements IntegrationEventSink
{
    public function __construct(private EventDispatcherInterface $dispatcher) {}

    #[Override]
    public function dispatch(IntegrationEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
