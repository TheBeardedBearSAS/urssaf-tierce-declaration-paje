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
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

/**
 * The commands that work on one employer run under the lock of the account, whatever the selector (ADR 0006).
 */
final class CommandLocksTest extends TestCase
{
    private const NUMBER = 'Y1869005780009';

    /**
     * @param array<string, string> $selector
     */
    #[DataProvider('selectors')]
    public function testAHeldLockOfTheEmployerSkipsEveryMandateCommandWhateverTheSelector(array $selector): void
    {
        $harness = Harness::boot([], false, true);
        $account = Scenario::importedEmployer($harness);
        $held = HeldLocks::hold($harness, OperationLocks::resource('mandate', $account->accountId));
        $selector = array_map(static fn(string $value): string => $value === '{account}' ? $account->accountId : $value, $selector);

        foreach (['urssaf:paje:mandate:register', 'urssaf:paje:mandate:import', 'urssaf:paje:mandate:cancel', 'urssaf:paje:mandate:record-cancellation', 'urssaf:paje:employer:forget'] as $name) {
            $command = $harness->command($name);

            static::assertSame(0, $command->execute($selector + (\in_array($name, ['urssaf:paje:mandate:cancel', 'urssaf:paje:employer:forget'], true) ? ['--force' => true] : [])), $name);
            static::assertStringContainsString(\sprintf('Already running (lock "urssaf_paje.mandate.%s" is held): skipped.', $account->accountId), $command->getDisplay(), $name);
        }
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_030));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
        $held->release();
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function selectors(): iterable
    {
        yield 'external reference' => [['--employer' => Scenario::EMPLOYER]];
        yield 'Pajemploi number' => [['--employer-number' => self::NUMBER]];
        yield 'account id' => [['--account-id' => '{account}']];
    }

    public function testNoPersonalIdentifierReachesALockName(): void
    {
        $harness = Harness::boot([], false, true);
        $account = Scenario::importedEmployer($harness);
        $held = HeldLocks::hold($harness, OperationLocks::resource('mandate', $account->accountId));
        $command = $harness->command('urssaf:paje:mandate:register');

        $command->execute(['--employer-number' => self::NUMBER]);

        static::assertStringNotContainsString(self::NUMBER, $command->getDisplay());
        $held->release();
    }

    public function testTheLockOfAnotherEmployerDoesNotBlock(): void
    {
        $harness = Harness::boot([], false, true);
        Scenario::importedEmployer($harness);
        $held = HeldLocks::hold($harness, OperationLocks::resource('mandate', 'another-account'));
        $command = $harness->command('urssaf:paje:mandate:register');

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_030));
        $held->release();
    }

    public function testTheLockIsReleasedAfterTheCommand(): void
    {
        $harness = Harness::boot([], false, true);
        Scenario::importedEmployer($harness);

        static::assertSame(0, $harness->command('urssaf:paje:mandate:register')->execute(['--employer' => Scenario::EMPLOYER]));
        $import = $harness->command('urssaf:paje:mandate:import');

        static::assertSame(0, $import->execute(['--employer' => Scenario::EMPLOYER]));
        static::assertStringNotContainsString('Already running', $import->getDisplay());
    }
}
