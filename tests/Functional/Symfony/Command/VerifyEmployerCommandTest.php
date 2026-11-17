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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;

final class VerifyEmployerCommandTest extends TestCase
{
    private const NAME = 'urssaf:paje:employer:verify';
    private const NUMBER = 'Y1869005780009';

    public function testAnEmployerIsVerifiedWithoutWritingAnything(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(0, $command->execute(['employer-number' => self::NUMBER]));

        static::assertSame([
            'Authorised' => 'yes',
            'Online account' => 'Actif',
            'Checked at' => '2026-11-15T10:00:00Z',
        ], Fields::of($command->getDisplay()));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
        static::assertStringNotContainsString(self::NUMBER, $command->getDisplay());
        static::assertStringNotContainsString("n'est pas actif", $command->getDisplay());
    }

    public function testAnInvalidBirthDateExitsWithTwoAndNeverCallsUrssaf(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-14-45']);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER]));

        static::assertStringContainsString('birth_date', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testItNeedsAnInteractiveSession(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['employer-number' => self::NUMBER], ['interactive' => false]));

        static::assertStringContainsString('Employer birth date (YYYY-MM-DD) is read from a hidden prompt: run the command interactively.', $command->getDisplay());
    }

    public function testAnUnknownEmployerExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_020, 400, 'error_pe_inexistant.json');
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(['employer-number' => self::NUMBER]));

        static::assertStringContainsString('rejected the request', $command->getDisplay());
    }

    public function testAnUnavailablePlatformExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_020, 503, '{}');
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(['employer-number' => self::NUMBER]));

        static::assertStringContainsString('temporarily unavailable', $command->getDisplay());
    }

    public function testAnAuthenticationFailureExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-02-23']);

        static::assertSame(1, $command->execute(['employer-number' => self::NUMBER]));
    }
}
