<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;

final class EnvironmentTest extends TestCase
{
    public function testSandboxTargetsTheEditorPlatform(): void
    {
        static::assertSame('https://api-edi.urssaf.fr', Environment::Sandbox->baseUri());
    }

    public function testProductionTargetsThePublicPlatform(): void
    {
        static::assertSame('https://api.urssaf.fr', Environment::Production->baseUri());
    }
}
