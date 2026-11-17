<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

final readonly class FailingEventPublisher implements EventPublisher
{
    public function __construct(public RuntimeException $failure) {}

    #[Override]
    public function publish(DomainEvent ...$events): void
    {
        throw $this->failure;
    }
}
