<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;

/**
 * Runs an action once, right before the next flush of an entity manager: the window in which a concurrent writer can
 * win the race against a uniqueness check that has already passed.
 */
final class BeforeNextFlush
{
    private bool $fired = false;

    /**
     * @param Closure(): void $action
     */
    private function __construct(private readonly Closure $action) {}

    /**
     * @param Closure(): void $action
     * @param-immediately-invoked-callable $action
     */
    public static function run(EntityManagerInterface $entityManager, Closure $action): void
    {
        $entityManager->getEventManager()->addEventListener(Events::preFlush, new self($action));
    }

    public function preFlush(): void
    {
        if ($this->fired) {
            return;
        }
        $this->fired = true;
        ($this->action)();
    }
}
