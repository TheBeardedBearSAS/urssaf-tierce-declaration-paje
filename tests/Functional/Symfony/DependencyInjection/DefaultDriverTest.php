<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\BundleConfiguration;

/**
 * The default persistence driver follows the installed ORM (ADR 0013).
 */
final class DefaultDriverTest extends TestCase
{
    public function testTheDriverIsDoctrineWhenTheOrmIsInstalled(): void
    {
        static::assertSame('doctrine', BundleConfiguration::defaultDriverFor(true));
    }

    public function testTheDriverIsInMemoryWithoutTheOrm(): void
    {
        static::assertSame('in_memory', BundleConfiguration::defaultDriverFor(false));
    }

    public function testTheRuntimeDefaultIsDoctrineBecauseTheOrmIsADevelopmentDependency(): void
    {
        static::assertSame('doctrine', BundleConfiguration::defaultDriver());
    }
}
