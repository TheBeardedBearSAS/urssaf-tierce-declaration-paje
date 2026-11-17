<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\ChildminderChildFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class ChildminderChildFactoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return ['birth_date' => '2015-01-12', 'last_name' => "L'HOMME", 'first_name' => 'JEAN-MICHEL'];
    }

    public function testFromInput(): void
    {
        $child = (new ChildminderChildFactory())->fromInput(self::valid() + ['disabled_child_care_days' => 10, 'consecutive_24h_days' => '3']);

        static::assertTrue($child->child()->equals(DeclarationSamples::child()));
        static::assertSame(10, $child->disabledChildCareDays());
        static::assertSame(3, $child->consecutive24hDays());
        static::assertSame([], DomainAssertions::summary((new ChildminderChildFactory())->validate(self::valid())));
    }

    public function testBothCountsAreOptional(): void
    {
        $child = (new ChildminderChildFactory())->fromInput(self::valid());

        static::assertNull($child->disabledChildCareDays());
        static::assertNull($child->consecutive24hDays());
    }

    public function testEveryMissingFieldIsReported(): void
    {
        static::assertSame(
            ['error birth_date birth_date.required', 'error last_name last_name.required', 'error first_name first_name.required'],
            DomainAssertions::summary((new ChildminderChildFactory())->validate([])),
        );
    }

    public function testTheInt32RangeIsReportedUnderTheInputKeys(): void
    {
        static::assertSame(
            ['error disabled_child_care_days disabled_child_care_days.out_of_range', 'error consecutive_24h_days consecutive_24h_days.out_of_range'],
            DomainAssertions::summary((new ChildminderChildFactory())->validate(self::valid() + ['disabled_child_care_days' => 2_147_483_648, 'consecutive_24h_days' => -2_147_483_649])),
        );
        static::assertSame(
            ['error disabled_child_care_days disabled_child_care_days.invalid_type'],
            DomainAssertions::summary((new ChildminderChildFactory())->validate(self::valid() + ['disabled_child_care_days' => 'x'])),
        );
    }

    public function testTheRulesOfThePresentFieldsAreReportedBesideAnInvalidChild(): void
    {
        static::assertSame(
            ['error last_name last_name.invalid_format', 'error consecutive_24h_days consecutive_24h_days.out_of_range'],
            DomainAssertions::summary((new ChildminderChildFactory())->validate(['birth_date' => '2015-01-12', 'last_name' => 'dupont', 'first_name' => 'PAUL', 'consecutive_24h_days' => 2_147_483_648])),
        );
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error first_name first_name.required'],
            DeclarationSamples::violations(static fn(): mixed => (new ChildminderChildFactory())->fromInput(['birth_date' => '2015-01-12', 'last_name' => 'A'])),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(ChildminderChildFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(ChildminderChildFactory::class, 'fromInput', ['input']);
    }
}
