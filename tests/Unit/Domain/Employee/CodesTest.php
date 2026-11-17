<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Civility;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class CodesTest extends TestCase
{
    public function testActivityTypeNamedConstructors(): void
    {
        static::assertSame('A', ActivityType::childminder()->value());
        static::assertSame('G', ActivityType::homeCare()->value());
        static::assertTrue(ActivityType::childminder()->isDocumented());
        static::assertTrue(ActivityType::homeCare()->isDocumented());
        static::assertTrue(ActivityType::childminder()->equals(ActivityType::of('A')));
        static::assertFalse(ActivityType::childminder()->equals(ActivityType::homeCare()));
    }

    public function testActivityTypeAcceptsAnyUndocumentedCharacter(): void
    {
        $type = ActivityType::of('X');

        static::assertSame('X', $type->value());
        static::assertFalse($type->isDocumented());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideWrongLengths(): iterable
    {
        yield 'empty' => ['', 'too_short'];
        yield 'two characters' => ['AB', 'too_long'];
    }

    #[DataProvider('provideWrongLengths')]
    public function testActivityTypeRejectsAWrongLength(string $value, string $reason): void
    {
        static::assertSame(
            'activity_type.' . $reason,
            DomainAssertions::assertViolationCode('activity_type.' . $reason, static fn(): ActivityType => ActivityType::of($value))->violationCode(),
        );
    }

    public function testCivilityNamedConstructors(): void
    {
        static::assertSame('1', Civility::mister()->value());
        static::assertSame('2', Civility::madam()->value());
        static::assertTrue(Civility::mister()->isDocumented());
        static::assertTrue(Civility::madam()->isDocumented());
        static::assertTrue(Civility::madam()->equals(Civility::of('2')));
        static::assertFalse(Civility::madam()->equals(Civility::mister()));
    }

    public function testCivilityAcceptsAnyUndocumentedCharacter(): void
    {
        $civility = Civility::of('3');

        static::assertSame('3', $civility->value());
        static::assertFalse($civility->isDocumented());
    }

    #[DataProvider('provideWrongLengths')]
    public function testCivilityRejectsAWrongLength(string $value, string $reason): void
    {
        static::assertSame(
            'civility.' . $reason,
            DomainAssertions::assertViolationCode('civility.' . $reason, static fn(): Civility => Civility::of($value))->violationCode(),
        );
    }
}
