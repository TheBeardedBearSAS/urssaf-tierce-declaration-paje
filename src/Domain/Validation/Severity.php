<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation;

enum Severity: string
{
    /** Blocks the operation: URSSAF would reject the request. */
    case Error = 'error';

    /** Informative only: the operation may proceed. */
    case Warning = 'warning';
}
