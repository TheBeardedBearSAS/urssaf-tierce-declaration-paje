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

/**
 * Delivers the pending integration events of the outbox, in recording order.
 */
final readonly class RelayOutbox
{
    /**
     * @param ?int $limit events read at most (null: the default page size of the workflow policy, capped by its maximum)
     */
    public function __construct(public ?int $limit) {}
}
