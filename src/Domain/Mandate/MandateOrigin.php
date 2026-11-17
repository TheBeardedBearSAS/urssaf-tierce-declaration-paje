<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate;

/**
 * How a {@see Mandate} came to be tracked (ADR 0006).
 */
enum MandateOrigin: string
{
    /** Registered by method 030 through this package (RegisterMandate). */
    case Registered = 'registered';

    /** Registered outside the package and adopted without any URSSAF call (ImportMandate). */
    case Imported = 'imported';
}
