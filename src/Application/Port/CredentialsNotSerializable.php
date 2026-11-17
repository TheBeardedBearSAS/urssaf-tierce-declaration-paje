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

use LogicException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;

final class CredentialsNotSerializable extends LogicException implements UrssafException
{
    public static function create(): self
    {
        return new self('API credentials must never be serialized.');
    }
}
