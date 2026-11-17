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

final class ImportEmployerCommandTest extends TestCase
{
    private const NAME = 'urssaf:paje:employer:import';
    private const NUMBER = 'Y1869005780009';

    public function testAnExistingEmployerIsTrackedWithoutAnyCall(): void
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
            'Origin' => 'imported',
            'Stage' => 'tracked',
            'Created at' => '2026-11-15T10:00:00Z',
        ], $fields);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        static::assertStringNotContainsString(self::NUMBER, $command->getDisplay());
    }

    public function testImportingTwiceIsIdempotent(): void
    {
        $harness = Harness::boot();
        $first = Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['employer-number' => self::NUMBER, '--reference' => Scenario::EMPLOYER]));

        static::assertSame($first->accountId, Fields::of($command->getDisplay())['Account id']);
    }

    public function testTheReferenceIsRequired(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER]));

        static::assertStringContainsString('The --reference option is required.', $command->getDisplay());
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('invalidInputs')]
    public function testAnInvalidInputExitsWithTwo(array $input, string $birthDate, string $message): void
    {
        $command = Harness::boot()->command(self::NAME);
        $command->setInputs([$birthDate]);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'malformed number' => [['employer-number' => 'x', '--reference' => 'employer-1'], '1975-02-23', 'employer_number'];
        yield 'malformed birth date' => [['employer-number' => self::NUMBER, '--reference' => 'employer-1'], '1975-14-45', 'birth_date'];
        yield 'empty birth date' => [['employer-number' => self::NUMBER, '--reference' => 'employer-1'], '', 'birth_date'];
    }

    public function testItNeedsAnInteractiveSession(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER, '--reference' => Scenario::EMPLOYER], ['interactive' => false]));

        static::assertStringContainsString('is read from a hidden prompt', $command->getDisplay());
    }

    public function testAnotherEmployerUnderTheSameReferenceExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(['employer-number' => 'Y2926822480004', '--reference' => Scenario::EMPLOYER]));
    }
}
