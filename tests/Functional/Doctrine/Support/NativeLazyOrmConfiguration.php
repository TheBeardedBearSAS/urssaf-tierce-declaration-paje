<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support;

/**
 * Stands for an ORM configuration that implements the native lazy objects.
 */
final class NativeLazyOrmConfiguration
{
    public function enableNativeLazyObjects(bool $enabled): bool
    {
        return $enabled;
    }
}
