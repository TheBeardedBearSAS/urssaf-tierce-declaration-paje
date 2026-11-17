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
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

/**
 * @mago-expect lint:too-many-methods
 */
final class MandateCommandsTest extends TestCase
{
    private const NUMBER = 'Y1869005780009';

    /**
     * @param array<string, string> $selector
     */
    #[DataProvider('selectors')]
    public function testTheMandateIsRegisteredWhateverTheSelector(array $selector): void
    {
        $harness = Harness::boot();
        $account = Scenario::importedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:register');

        static::assertSame(0, $command->execute(array_map(static fn(string $value): string => $value === '{account}' ? $account->accountId : $value, $selector)));

        $fields = Fields::of($command->getDisplay());
        static::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $fields['Mandate id']);
        unset($fields['Mandate id']);
        static::assertSame([
            'External reference' => Scenario::EMPLOYER,
            'Account id' => $account->accountId,
            'Origin' => 'registered',
            'State' => 'registered',
            'Registered at' => '2026-11-15T10:00:00Z',
            'Cancelled at' => '-',
        ], $fields);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_030));
        static::assertStringNotContainsString(self::NUMBER, $command->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function selectors(): iterable
    {
        yield 'external reference' => [['--employer' => Scenario::EMPLOYER]];
        yield 'Pajemploi number' => [['--employer-number' => self::NUMBER, '--tenant' => 'tenant-1']];
        yield 'account id' => [['--account-id' => '{account}']];
    }

    public function testRegisteringAgainDoesNotCallUrssafAgain(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:register');

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_030));
    }

    public function testTheMandateIsImportedWithoutAnyCall(): void
    {
        $harness = Harness::boot();
        $account = Scenario::importedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:import');

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        $fields = Fields::of($command->getDisplay());
        static::assertSame('imported', $fields['Origin']);
        static::assertSame('registered', $fields['State']);
        static::assertSame($account->accountId, $fields['Account id']);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_030));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testTheMandateIsCancelledAfterAConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:cancel');
        $command->setInputs(['yes']);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('Cancel the mandate of this employer at URSSAF? [y/N]', $command->getDisplay());
        static::assertStringNotContainsString(Scenario::EMPLOYER . ' at URSSAF', $command->getDisplay());
        $fields = Fields::of($command->getDisplay());
        static::assertSame('cancelled', $fields['State']);
        static::assertSame('2026-11-15T10:00:00Z', $fields['Cancelled at']);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    public function testTheCancellationIsAbortedWithoutConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:cancel');
        $command->setInputs(['no']);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('Aborted: nothing was sent to URSSAF.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    public function testAnEmptyAnswerIsNotAConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:cancel');
        $command->setInputs(['']);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('Aborted: nothing was sent to URSSAF.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    public function testForceSkipsTheConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:cancel');

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true], ['interactive' => false]));

        static::assertStringNotContainsString('[y/N]', $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    public function testTheCancellationNeedsAConfirmationWhenNotInteractive(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:cancel');

        static::assertSame(2, $command->execute(['--employer' => Scenario::EMPLOYER], ['interactive' => false]));

        static::assertStringContainsString('Cancelling a mandate needs a confirmation: use --force when not interactive.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    public function testACancellationIsRecordedWithoutAnyCall(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command('urssaf:paje:mandate:record-cancellation');

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertSame('cancelled', Fields::of($command->getDisplay())['State']);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    /**
     * @return array<string, bool>
     */
    private static function confirmed(string $name): array
    {
        return $name === 'urssaf:paje:mandate:cancel' ? ['--force' => true] : [];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mandateCommands(): iterable
    {
        yield 'register' => ['urssaf:paje:mandate:register'];
        yield 'import' => ['urssaf:paje:mandate:import'];
        yield 'cancel' => ['urssaf:paje:mandate:cancel'];
        yield 'record-cancellation' => ['urssaf:paje:mandate:record-cancellation'];
    }

    #[DataProvider('mandateCommands')]
    public function testAnInvalidSelectorExitsWithTwoAndNeverCallsUrssaf(string $name): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);

        foreach ([[], ['--employer' => 'employer-1', '--account-id' => 'a'], ['--employer-number' => 'x'], ['--account-id' => '']] as $input) {
            $command = $harness->command($name);

            static::assertSame(2, $command->execute($input + self::confirmed($name)));
        }
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_030));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    #[DataProvider('mandateCommands')]
    public function testAnUnknownEmployerExitsWithOne(string $name): void
    {
        $harness = Harness::boot();
        $command = $harness->command($name);

        static::assertSame(1, $command->execute(['--employer' => 'unknown'] + self::confirmed($name)));

        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_030));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    #[DataProvider('mandateCommands')]
    public function testAnEmployerOfAnotherTenantIsUnknown(string $name): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        Scenario::importedEmployer($harness);
        $command = $harness->command($name);

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER, '--tenant' => Accounts::SECOND_TENANT] + self::confirmed($name)));
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_030, 400, 'error_param_invalide.json');
        $command = $harness->command('urssaf:paje:mandate:register');

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('rejected the request', $command->getDisplay());
    }

    public function testAMandateHeldElsewhereExitsWithOneAndRecordsNothing(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_030, 400, 'error_compte_deja_mandate.json');

        $register = $harness->command('urssaf:paje:mandate:register');
        static::assertSame(1, $register->execute(['--employer' => Scenario::EMPLOYER]));

        $cancel = $harness->command('urssaf:paje:mandate:cancel');
        static::assertSame(1, $cancel->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));
    }

    public function testAServerFailureExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_030, 503, '{}');
        $command = $harness->command('urssaf:paje:mandate:register');

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('The outcome of URSSAF method 030 is unknown', $command->getDisplay());
    }

    public function testAnUnknownOutcomeExitsWithOneAndPointsToTheCheck(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_030, 500, '{}');
        $command = $harness->command('urssaf:paje:mandate:register');

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('must not be resent automatically', $command->getDisplay());
    }
}
