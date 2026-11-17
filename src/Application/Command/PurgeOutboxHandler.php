<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

final readonly class PurgeOutboxHandler
{
    public function __construct(private Outbox $outbox, private Clock $clock, private WorkflowPolicy $policy) {}

    /**
     * @return int the number of deleted entries
     */
    public function __invoke(PurgeOutbox $command): int
    {
        $retention = $command->retention ?? $this->policy->outboxRetention();

        return $this->outbox->purgeDispatchedBefore(Instant::fromDateTime($this->clock->now()->sub($retention)));
    }
}
