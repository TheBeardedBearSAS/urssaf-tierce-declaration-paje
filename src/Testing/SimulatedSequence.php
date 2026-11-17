<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Testing;

/**
 * Numbered identifiers of the simulator (1, 2, 3...), formatted with a sprintf format holding one `%d`.
 *
 * @internal
 */
final class SimulatedSequence
{
    private int $last = 0;

    public function __construct(private readonly string $format) {}

    public function next(): string
    {
        ++$this->last;

        return \sprintf($this->format, $this->last);
    }
}
