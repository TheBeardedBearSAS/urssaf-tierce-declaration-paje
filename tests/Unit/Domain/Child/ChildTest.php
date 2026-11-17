<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Child;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class ChildTest extends TestCase
{
    private static function child(string $lastName = "L'HOMME", string $firstName = 'JEAN-MICHEL', string $birthDate = '2015-01-12'): Child
    {
        return Child::of(
            CalendarDate::fromString($birthDate),
            UpperCaseName::of($lastName, 'child_last_name'),
            UpperCaseName::of($firstName, 'child_first_name'),
        );
    }

    public function testOfExposesTheFields(): void
    {
        $child = self::child();

        static::assertSame('2015-01-12', $child->birthDate()->toString());
        static::assertSame("L'HOMME", $child->lastName()->value());
        static::assertSame('JEAN-MICHEL', $child->firstName()->value());
    }

    public function testEquality(): void
    {
        static::assertTrue(self::child()->equals(self::child()));
        static::assertFalse(self::child()->equals(self::child("L'HOMME", 'JEAN-MICHEL', '2015-01-13')));
        static::assertFalse(self::child()->equals(self::child('DUPONT', 'JEAN-MICHEL', '2015-01-12')));
        static::assertFalse(self::child()->equals(self::child("L'HOMME", 'PAUL', '2015-01-12')));
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $dump = print_r(self::child(), true);

        static::assertStringNotContainsString('HOMME', $dump);
        static::assertStringNotContainsString('2015', $dump);
        static::assertStringContainsString('***', $dump);
    }

    public function testBirthDateIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(Child::class, 'of', ['birthDate']);
        DomainAssertions::assertSensitiveParameters(Child::class, '__construct', ['birthDate']);
    }
}
