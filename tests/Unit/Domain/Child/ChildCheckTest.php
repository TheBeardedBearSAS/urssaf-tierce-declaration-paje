<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Child;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlement;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlementStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;

final class ChildCheckTest extends TestCase
{
    private static function child(): Child
    {
        return Child::of(
            CalendarDate::fromString('2015-01-12'),
            UpperCaseName::of("L'HOMME", 'child_last_name'),
            UpperCaseName::of('JEAN-MICHEL', 'child_first_name'),
        );
    }

    public function testStatusKeepsTheRawIntegerAndMapsDocumentedValues(): void
    {
        static::assertSame(ChildEntitlement::NotEntitled, ChildEntitlementStatus::of(0)->documented());
        static::assertSame(ChildEntitlement::Entitled, ChildEntitlementStatus::of(1)->documented());
        static::assertSame(ChildEntitlement::EntitledPartialMatch, ChildEntitlementStatus::of(2)->documented());
        static::assertNull(ChildEntitlementStatus::of(3)->documented());
        static::assertNull(ChildEntitlementStatus::of(-1)->documented());
        static::assertSame(3, ChildEntitlementStatus::of(3)->raw());
        static::assertSame([0, 1, 2], array_column(ChildEntitlement::cases(), 'value'));
    }

    public function testStatusEquality(): void
    {
        static::assertTrue(ChildEntitlementStatus::of(1)->equals(ChildEntitlementStatus::of(1)));
        static::assertFalse(ChildEntitlementStatus::of(1)->equals(ChildEntitlementStatus::of(2)));
    }

    public function testCheckExposesTheChildAndTheStatus(): void
    {
        $child = self::child();
        $status = ChildEntitlementStatus::of(1);

        $check = ChildCheck::of($child, $status);

        static::assertSame($child, $check->child());
        static::assertSame($status, $check->status());
        static::assertNull(ChildCheck::of(null, $status)->child());
    }

    public function testCheckIsMaskedInDebugOutput(): void
    {
        $dump = print_r(ChildCheck::of(self::child(), ChildEntitlementStatus::of(1)), true);

        static::assertStringNotContainsString('HOMME', $dump);
        static::assertStringContainsString('***', $dump);
        static::assertStringContainsString('1', $dump);
    }

    public function testDebugInfoKeepsTheRawStatusAsAnInteger(): void
    {
        static::assertSame(
            ['status' => 7, 'child' => '***'],
            ChildCheck::of(null, ChildEntitlementStatus::of(7))->__debugInfo(),
        );
    }
}
