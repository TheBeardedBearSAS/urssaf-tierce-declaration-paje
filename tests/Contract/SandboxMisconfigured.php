<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use RuntimeException;
use Throwable;

/**
 * A sandbox variable is set but wrong. The message names the variable and the rule, never the value (it may be
 * personal data).
 */
final class SandboxMisconfigured extends RuntimeException
{
    public static function variable(string $name, string $rule, ?Throwable $previous): self
    {
        return new self(\sprintf('%s %s.', $name, $rule), 0, $previous);
    }
}
