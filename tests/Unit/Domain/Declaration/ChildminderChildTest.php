<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;

final class ChildminderChildTest extends TestCase
{
    public function testExposesTheChildAndBothCounts(): void
    {
        $child = DeclarationSamples::childminderChild(10, 3);

        static::assertTrue($child->child()->birthDate()->equals(DeclarationSamples::child()->birthDate()));
        static::assertSame(10, $child->disabledChildCareDays());
        static::assertSame(3, $child->consecutive24hDays());
    }

    public function testBothCountsAreOptional(): void
    {
        $child = DeclarationSamples::childminderChild();

        static::assertNull($child->disabledChildCareDays());
        static::assertNull($child->consecutive24hDays());
    }

    public function testInt32BoundsAreAccepted(): void
    {
        $min = DeclarationSamples::childminderChild(-2_147_483_648, 2_147_483_647);

        static::assertSame(-2_147_483_648, $min->disabledChildCareDays());
        static::assertSame(2_147_483_647, $min->consecutive24hDays());
    }

    public function testCountsAreNotCheckedAgainstTheActivityDays(): void
    {
        static::assertSame(500, DeclarationSamples::childminderChild(500, 500)->consecutive24hDays());
    }

    public function testValuesOutsideInt32AreRefused(): void
    {
        static::assertSame(
            [
                'error disabledChildCareDays disabled_child_care_days.out_of_range',
                'error consecutive24hDays consecutive_24h_days.out_of_range',
            ],
            DeclarationSamples::violations(static fn(): ChildminderChild => DeclarationSamples::childminderChild(
                -2_147_483_649,
                2_147_483_648,
            )),
        );
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $dump = print_r(DeclarationSamples::childminderChild(4242, 7), true);

        static::assertStringNotContainsString('4242', $dump);
        static::assertStringContainsString('***', $dump);
    }
}
