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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\LockRegistrar;

/**
 * The lock factory argument for both availabilities of symfony/lock.
 */
final class LockRegistrarTest extends TestCase
{
    public function testTheFactoryIsMandatoryWhenTheLocksAreRequired(): void
    {
        $factory = LockRegistrar::factoryArgument(['enabled' => true, 'factory' => 'my.factory', 'ttl' => 60.0], true);

        static::assertEquals(new Reference('my.factory'), $factory);
    }

    public function testTheFactoryIsOptionalInAutoMode(): void
    {
        $factory = LockRegistrar::factoryArgument(['enabled' => 'auto', 'factory' => 'my.factory', 'ttl' => 60.0], true);

        static::assertEquals(new Reference('my.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE), $factory);
    }

    public function testThereIsNoFactoryWhenTheLocksAreDisabled(): void
    {
        static::assertNull(LockRegistrar::factoryArgument(['enabled' => false, 'factory' => 'my.factory', 'ttl' => 60.0], true));
    }

    public function testThereIsNoFactoryWithoutTheComponent(): void
    {
        static::assertNull(LockRegistrar::factoryArgument(['enabled' => true, 'factory' => 'my.factory', 'ttl' => 60.0], false));
        static::assertNull(LockRegistrar::factoryArgument(['enabled' => 'auto', 'factory' => 'my.factory', 'ttl' => 60.0], false));
    }
}
