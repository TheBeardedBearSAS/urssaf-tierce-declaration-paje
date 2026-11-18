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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ReconcileStaleDeclarationsCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\RelayOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ShowDeclarationCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\UrssafTierceDeclarationPajeBundle;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App\TestKernel;

/**
 * The definitions of the console commands, without booting a kernel (a booted container is shared between the tests of a
 * process, so the tests that boot one do not each run the registration).
 */
final class CommandRegistrarTest extends TestCase
{
    public function testTheReconciliationIsRegisteredWithoutTheOutbox(): void
    {
        $container = self::load([]);

        static::assertTrue($container->hasDefinition(ReconcileStaleDeclarationsCommand::class));
        static::assertTrue($container->getDefinition(ReconcileStaleDeclarationsCommand::class)->hasTag('console.command'));
        static::assertTrue($container->hasDefinition(ShowDeclarationCommand::class));
        static::assertFalse($container->hasDefinition(RelayOutboxCommand::class));
        static::assertFalse($container->hasDefinition(PurgeOutboxCommand::class));
    }

    public function testTheReconciliationAndTheOutboxCommandsAreRegisteredWithTheOutbox(): void
    {
        $container = self::load(['outbox' => ['enabled' => true]]);

        foreach ([ReconcileStaleDeclarationsCommand::class, RelayOutboxCommand::class, PurgeOutboxCommand::class] as $command) {
            static::assertTrue($container->hasDefinition($command), $command);
            static::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.build_dir', \dirname(__DIR__, 4) . '/var/t5');
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.project_dir', \dirname(__DIR__, 4));
        $extension = (new UrssafTierceDeclarationPajeBundle())->getContainerExtension();
        static::assertNotNull($extension);
        $extension->load([[...TestKernel::defaults(), ...$config]], $container);

        return $container;
    }
}
