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

final class TrackEmployerCommandTest extends TestCase
{
    private const NAME = 'urssaf:paje:employer:track';
    private const NUMBER = 'Y1869005780009';

    public function testAnEmployerIsVerifiedThenTracked(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['employer-number' => self::NUMBER, '--reference' => Scenario::EMPLOYER]));

        $fields = Fields::of($command->getDisplay());
        static::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $fields['Account id']);
        unset($fields['Account id']);
        static::assertSame([
            'External reference' => Scenario::EMPLOYER,
            'Origin' => 'tracked',
            'Stage' => 'tracked',
            'Created at' => '2026-11-15T10:00:00Z',
        ], $fields);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
        static::assertStringNotContainsString(self::NUMBER, $command->getDisplay());
    }

    public function testTheTenantOptionChoosesTheTenant(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['employer-number' => self::NUMBER, '--reference' => Scenario::EMPLOYER, '--tenant' => Accounts::SECOND_TENANT]));
    }

    public function testSeveralTenantsNeedTheOption(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER, '--reference' => Scenario::EMPLOYER]));

        static::assertStringContainsString('The --tenant option is required (configured tenants: 2).', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testTheReferenceIsRequired(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER]));

        static::assertStringContainsString('The --reference option is required.', $command->getDisplay());
        static::assertStringNotContainsString('birth date', $command->getDisplay());
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('invalidInputs')]
    public function testAnInvalidInputExitsWithTwoAndNeverCallsUrssaf(array $input, string $birthDate, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);
        $command->setInputs([$birthDate]);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'malformed number' => [['employer-number' => 'x', '--reference' => 'employer-1'], '1975-02-23', 'employer_number'];
        yield 'malformed birth date' => [['employer-number' => self::NUMBER, '--reference' => 'employer-1'], '1975-14-45', 'birth_date'];
        yield 'empty reference' => [['employer-number' => self::NUMBER, '--reference' => ''], '1975-02-23', '(external_reference.empty)'];
    }

    public function testItNeedsAnInteractiveSession(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER, '--reference' => Scenario::EMPLOYER], ['interactive' => false]));

        static::assertStringContainsString('Employer birth date (YYYY-MM-DD) is read from a hidden prompt: run the command interactively.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testAnExternalReferenceOfAnotherEmployerExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness, Scenario::TENANT, Scenario::EMPLOYER);
        $command = $harness->command(self::NAME);
        $command->setInputs(['1980-01-01']);

        static::assertSame(1, $command->execute(['employer-number' => 'Y2926822480004', '--reference' => Scenario::EMPLOYER]));
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_020, 400, 'error_param_invalide.json');
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(['employer-number' => self::NUMBER, '--reference' => Scenario::EMPLOYER]));

        static::assertStringContainsString('rejected the request', $command->getDisplay());
    }
}
