<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf;

/**
 * Target URSSAF platform. The sandbox ("environnement Éditeur") host must stay overridable by configuration.
 */
enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public function baseUri(): string
    {
        return match ($this) {
            self::Sandbox => 'https://api-edi.urssaf.fr',
            self::Production => 'https://api.urssaf.fr',
        };
    }
}
