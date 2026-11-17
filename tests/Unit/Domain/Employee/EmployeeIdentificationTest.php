<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentificationKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class EmployeeIdentificationTest extends TestCase
{
    public function testByNumber(): void
    {
        $number = PajeEmployeeNumber::of('Y2926822480004');
        $birthDate = CalendarDate::fromString('1979-01-01');

        $identification = EmployeeByNumber::of($number, $birthDate);

        static::assertSame(EmployeeIdentificationKind::Number, $identification->kind());
        static::assertSame($number, $identification->number());
        static::assertSame($birthDate, $identification->birthDate());
        static::assertNull(EmployeeByNumber::of($number)->birthDate());
    }

    public function testByNir(): void
    {
        $nir = Nir::of('178010701005265');
        $birthDate = CalendarDate::fromString('1979-01-01');

        $identification = EmployeeByNir::of($nir, $birthDate);

        static::assertSame(EmployeeIdentificationKind::Nir, $identification->kind());
        static::assertSame($nir, $identification->nir());
        static::assertSame($birthDate, $identification->birthDate());
        static::assertNull(EmployeeByNir::of($nir)->birthDate());
    }

    public function testByAdministrativeData(): void
    {
        $identity = AdministrativeIdentity::of(null, UpperCaseName::of('DUPONT', 'birth_name'), null, null, null, null, null, null);
        $birthDate = CalendarDate::fromString('1979-01-01');

        $identification = EmployeeByAdministrativeData::of($identity, $birthDate);

        static::assertSame(EmployeeIdentificationKind::AdministrativeData, $identification->kind());
        static::assertSame($identity, $identification->identity());
        static::assertSame($birthDate, $identification->birthDate());
        static::assertNull(EmployeeByAdministrativeData::of($identity)->birthDate());
    }

    public function testByAdministrativeDataRefusesAnEmptyIdentity(): void
    {
        $first = DomainAssertions::assertViolationCode('employee_identification.empty', static function (): void {
            EmployeeByAdministrativeData::of(AdministrativeIdentity::of(null, null, null, null, null, null, null, null));
        });
        $second = DomainAssertions::assertViolationCode('employee_identification.empty', static function (): void {
            EmployeeByAdministrativeData::of(AdministrativeIdentity::of(null, null, null, null, null, '', '', ''), CalendarDate::fromString('1979-01-01'));
        });

        static::assertInstanceOf(InvalidValue::class, $first);
        static::assertInstanceOf(InvalidValue::class, $second);
    }

    public function testOnlyNumberAndNirAreDeclaredEmployees(): void
    {
        static::assertTrue((new ReflectionClass(EmployeeByNumber::class))->implementsInterface(DeclaredEmployee::class));
        static::assertTrue((new ReflectionClass(EmployeeByNir::class))->implementsInterface(DeclaredEmployee::class));
        static::assertFalse((new ReflectionClass(EmployeeByAdministrativeData::class))->implementsInterface(DeclaredEmployee::class));
        static::assertTrue((new ReflectionClass(EmployeeByAdministrativeData::class))->implementsInterface(EmployeeIdentification::class));
        static::assertTrue((new ReflectionClass(DeclaredEmployee::class))->implementsInterface(EmployeeIdentification::class));
    }

    public function testKindValues(): void
    {
        static::assertSame(['number', 'nir', 'administrative_data'], array_column(EmployeeIdentificationKind::cases(), 'value'));
    }

    public function testIdentificationsAreMaskedInDebugOutput(): void
    {
        $identity = AdministrativeIdentity::of(null, UpperCaseName::of('DUPONT', 'birth_name'), null, null, null, null, null, null);
        $dumps = [
            print_r(EmployeeByNumber::of(PajeEmployeeNumber::of('Y2926822480004'), CalendarDate::fromString('1979-01-01')), true),
            print_r(EmployeeByNir::of(Nir::of('178010701005265'), CalendarDate::fromString('1979-01-01')), true),
            print_r(EmployeeByAdministrativeData::of($identity, CalendarDate::fromString('1979-01-01')), true),
        ];

        foreach ($dumps as $dump) {
            static::assertStringNotContainsString('Y2926822480004', $dump);
            static::assertStringNotContainsString('178010701005265', $dump);
            static::assertStringNotContainsString('1979', $dump);
            static::assertStringContainsString('***', $dump);
        }
    }

    public function testByNumberBirthDateIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(EmployeeByNumber::class, 'of', ['birthDate']);
        DomainAssertions::assertSensitiveParameters(EmployeeByNumber::class, '__construct', ['birthDate']);
    }

    public function testByNirBirthDateIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(EmployeeByNir::class, 'of', ['birthDate']);
        DomainAssertions::assertSensitiveParameters(EmployeeByNir::class, '__construct', ['birthDate']);
    }

    public function testByAdministrativeDataBirthDateIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(EmployeeByAdministrativeData::class, 'of', ['birthDate']);
        DomainAssertions::assertSensitiveParameters(EmployeeByAdministrativeData::class, '__construct', ['birthDate']);
    }
}
