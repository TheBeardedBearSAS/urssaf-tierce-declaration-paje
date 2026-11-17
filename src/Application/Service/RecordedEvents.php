<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

/**
 * Domain events released by the aggregates during one {@see UnitOfWork}, in release order.
 */
final class RecordedEvents
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function record(DomainEvent ...$events): void
    {
        $this->events = [...$this->events, ...array_values($events)];
    }

    /**
     * @return list<DomainEvent>
     */
    public function all(): array
    {
        return $this->events;
    }
}
