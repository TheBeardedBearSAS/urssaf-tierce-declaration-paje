<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;

final class EmployerStageTest extends TestCase
{
    public function testValues(): void
    {
        static::assertSame(['tracked', 'mandate_registered', 'mandate_cancelled'], array_map(static fn(EmployerStage $stage): string => $stage->value, EmployerStage::cases()));
        static::assertSame(['tracked', 'imported'], array_map(static fn(EmployerOrigin $origin): string => $origin->value, EmployerOrigin::cases()));
    }

    public function testAfterMandateRegistered(): void
    {
        foreach (EmployerStage::cases() as $stage) {
            static::assertSame(EmployerStage::MandateRegistered, $stage->afterMandateRegistered(), $stage->value);
        }
    }

    public function testAfterMandateCancelled(): void
    {
        foreach (EmployerStage::cases() as $stage) {
            static::assertSame(EmployerStage::MandateCancelled, $stage->afterMandateCancelled(), $stage->value);
        }
    }
}
