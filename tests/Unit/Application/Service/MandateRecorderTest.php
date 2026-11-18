<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;

/**
 * A recording that meets no conflict runs exactly once: the attempts are retries, never repetitions.
 */
final class MandateRecorderTest extends TestCase
{
    public function testARegistrationWithoutConflictRunsOneAttempt(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();

        $mandate = $world->recorder->recordRegistration(EmployerAccountId::fromString('account-1'));

        static::assertSame('id-1', $mandate->id()->value());
        static::assertCount(1, array_keys($world->journal->entries(), 'begin', true));
        static::assertCount(1, $world->mandates->all());
    }

    public function testAnImportWithoutConflictRunsOneAttempt(): void
    {
        $world = new EmployerWorld();
        $world->trackedAccount();

        $mandate = $world->recorder->recordImport(EmployerAccountId::fromString('account-1'));

        static::assertSame('id-1', $mandate->id()->value());
        static::assertCount(1, array_keys($world->journal->entries(), 'begin', true));
        static::assertCount(1, $world->mandates->all());
    }

    public function testACancellationWithoutConflictRunsOneAttempt(): void
    {
        $world = new EmployerWorld();
        $mandate = $world->registeredMandate($world->trackedAccount());

        $cancelled = $world->recorder->recordCancellation(EmployerAccountId::fromString('account-1'), MandateId::fromString('mandate-1'));

        static::assertSame($mandate->id()->value(), $cancelled->id()->value());
        static::assertCount(1, array_keys($world->journal->entries(), 'begin', true));
    }
}
