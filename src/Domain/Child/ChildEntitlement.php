<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child;

/**
 * Documented values of `reponseStatutEnfant` (api-reference §3.1).
 */
enum ChildEntitlement: int
{
    /** Entitled child not found, or not entitled. */
    case NotEntitled = 0;

    /** Entitled child found. */
    case Entitled = 1;

    /** Entitled child found through a partial search. */
    case EntitledPartialMatch = 2;
}
