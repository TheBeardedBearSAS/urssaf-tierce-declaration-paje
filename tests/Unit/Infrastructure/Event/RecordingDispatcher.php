<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * PSR-14 dispatcher fake recording every dispatched object, in order.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $dispatched = [];

    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;

        return $event;
    }

    /**
     * @return list<object>
     */
    public function dispatched(): array
    {
        return $this->dispatched;
    }
}
