<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;

/**
 * The console commands of the bundle (`urssaf:paje:...`, ADR 0013).
 */
final class CommandsRegistrationTest extends TestCase
{
    private const TENANT_COMMANDS = [
        'urssaf:paje:employer:track',
        'urssaf:paje:employer:import',
        'urssaf:paje:employer:verify',
        'urssaf:paje:employer:forget',
        'urssaf:paje:mandate:register',
        'urssaf:paje:mandate:import',
        'urssaf:paje:mandate:cancel',
        'urssaf:paje:mandate:record-cancellation',
        'urssaf:paje:employee:verify',
        'urssaf:paje:employee:associate',
        'urssaf:paje:children:verify',
        'urssaf:paje:estimate',
        'urssaf:paje:predeclare',
        'urssaf:paje:declare',
        'urssaf:paje:declaration:resolve',
        'urssaf:paje:declarations:requeue-invalid-credentials',
        'urssaf:paje:declaration:show',
        'urssaf:paje:declarations:list',
    ];

    private const OTHER_COMMANDS = [
        'urssaf:paje:employers:sync-status',
        'urssaf:paje:declarations:reconcile-stale',
    ];

    private const OUTBOX_COMMANDS = ['urssaf:paje:outbox:relay', 'urssaf:paje:outbox:purge'];

    /**
     * @return list<string>
     */
    private static function names(Harness $harness): array
    {
        $names = [];
        foreach ((new Application($harness->kernel))->all('urssaf') as $key => $command) {
            $name = $command->getName();
            if ($name === null || $key !== $name) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    public function testEveryCommandIsRegisteredUnderTheNamespaceOfThePackage(): void
    {
        $names = self::names(Harness::boot());

        static::assertSame([], array_diff([...self::TENANT_COMMANDS, ...self::OTHER_COMMANDS], $names));
        foreach ($names as $name) {
            static::assertStringStartsWith('urssaf:paje:', $name);
        }
    }

    public function testTheOutboxCommandsComeWithTheOutbox(): void
    {
        $without = self::names(Harness::boot());
        $with = self::names(Harness::boot(['outbox' => ['enabled' => true]]));

        static::assertSame([], array_intersect(self::OUTBOX_COMMANDS, $without));
        static::assertSame(self::OUTBOX_COMMANDS, array_values(array_intersect($with, self::OUTBOX_COMMANDS)));
        static::assertSame([], array_diff([...self::TENANT_COMMANDS, ...self::OTHER_COMMANDS], $with));
    }

    public function testTheOldNameOfTheReconciliationIsAnAlias(): void
    {
        $harness = Harness::boot();
        $application = new Application($harness->kernel);

        static::assertSame('urssaf:paje:declarations:reconcile-stale', $application->find('urssaf:paje:declarations:reconcile')->getName());
    }

    #[DataProvider('tenantCommands')]
    public function testTheCommandsOfATenantFallBackOnTheConfiguredTenant(string $name): void
    {
        $harness = Harness::boot();
        $definition = (new Application($harness->kernel))->find($name)->getDefinition();

        static::assertTrue($definition->hasOption('tenant'), $name);
        static::assertTrue($definition->getOption('tenant')->isValueRequired(), $name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tenantCommands(): iterable
    {
        foreach (self::TENANT_COMMANDS as $name) {
            yield $name => [$name];
        }
    }

    public function testNoCommandAcceptsAPersonalValueAsAnOption(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true]]);
        $application = new Application($harness->kernel);

        foreach ($application->all('urssaf:paje') as $command) {
            foreach (array_keys($command->getDefinition()->getOptions()) as $option) {
                static::assertNotContains($option, ['birth-date', 'employer-birth-date', 'nir-value', 'identity', 'creation', 'children'], \sprintf('%s --%s', $command->getName() ?? '', $option));
            }
        }
    }
}
