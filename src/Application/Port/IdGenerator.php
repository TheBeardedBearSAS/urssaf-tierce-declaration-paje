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

/**
 * Generates unique identifiers (UUID v4 recommended) for local records, events and request correlation.
 */
interface IdGenerator
{
    /**
     * @return non-empty-string
     */
    public function generate(): string;
}
