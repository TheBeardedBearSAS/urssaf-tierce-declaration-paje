<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class EmployerReferenceTest extends TestCase
{
    public function testOfExposesTheNumberAndTheBirthDate(): void
    {
        $number = PajeEmployerNumber::of('Y1869005780009');
        $birthDate = CalendarDate::fromString('1975-02-23');

        $reference = EmployerReference::of($number, $birthDate);

        static::assertSame($number, $reference->number());
        static::assertSame($birthDate, $reference->birthDate());
    }

    public function testEquality(): void
    {
        $reference = EmployerReference::of(PajeEmployerNumber::of('Y1869005780009'), CalendarDate::fromString('1975-02-23'));

        static::assertTrue($reference->equals(EmployerReference::of(PajeEmployerNumber::of('Y1869005780009'), CalendarDate::fromString('1975-02-23'))));
        static::assertFalse($reference->equals(EmployerReference::of(PajeEmployerNumber::of('Y1869005780009'), CalendarDate::fromString('1975-02-24'))));
        static::assertFalse($reference->equals(EmployerReference::of(PajeEmployerNumber::of('Y2926822480004'), CalendarDate::fromString('1975-02-23'))));
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $dump = print_r(EmployerReference::of(PajeEmployerNumber::of('Y1869005780009'), CalendarDate::fromString('1975-02-23')), true);

        static::assertStringNotContainsString('Y1869005780009', $dump);
        static::assertStringNotContainsString('1975', $dump);
        static::assertStringContainsString('***', $dump);
    }

    public function testBirthDateIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(EmployerReference::class, 'of', ['birthDate']);
        DomainAssertions::assertSensitiveParameters(EmployerReference::class, '__construct', ['birthDate']);
    }
}
