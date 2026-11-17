<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing;

use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

/**
 * Fictitious data of the simulator tests.
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatorSamples
{
    public const EMPLOYER_NUMBER = 'Y1869005780009';
    public const OTHER_EMPLOYER_NUMBER = 'Y2926822480004';
    public const EMPLOYEE_NUMBER = 'Z1234567890123';
    public const BIRTH_DATE = '1975-02-23';

    private function __construct() {}

    public static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function otherTenant(): TenantId
    {
        return TenantId::fromString('tenant-2');
    }

    public static function employerNumber(string $value = self::EMPLOYER_NUMBER): PajeEmployerNumber
    {
        return PajeEmployerNumber::of($value);
    }

    public static function employer(string $number = self::EMPLOYER_NUMBER, string $birthDate = self::BIRTH_DATE): EmployerReference
    {
        return EmployerReference::of(self::employerNumber($number), CalendarDate::fromString($birthDate));
    }

    public static function employeeNumber(): PajeEmployeeNumber
    {
        return PajeEmployeeNumber::of(self::EMPLOYEE_NUMBER);
    }

    public static function byNumber(string $number = self::EMPLOYEE_NUMBER): DeclaredEmployee
    {
        return EmployeeByNumber::of(PajeEmployeeNumber::of($number));
    }

    public static function byNir(string $nir = '275037512345678'): DeclaredEmployee
    {
        return EmployeeByNir::of(Nir::of($nir));
    }

    public static function identity(): EmployeeIdentification
    {
        return self::byIdentity('DUPONT', null, 'MARIE', '1980-01-30', 'PARIS', '100');
    }

    /**
     * @mago-expect lint:excessive-parameter-list
     */
    public static function byIdentity(
        string $birthName,
        ?string $usageName,
        string $firstName,
        string $birthDate,
        string $city,
        string $country,
    ): EmployeeIdentification {
        return EmployeeByAdministrativeData::of(AdministrativeIdentity::of(
            null,
            UpperCaseName::of($birthName, 'birth_name'),
            $usageName === null ? null : UpperCaseName::of($usageName, 'usage_name'),
            UpperCaseName::of($firstName, 'first_name'),
            CalendarDate::fromString($birthDate),
            $country,
            null,
            $city,
        ));
    }

    public static function partialIdentity(?string $birthName, ?string $firstName, ?string $birthDate): EmployeeIdentification
    {
        return EmployeeByAdministrativeData::of(AdministrativeIdentity::of(
            null,
            $birthName === null ? null : UpperCaseName::of($birthName, 'birth_name'),
            null,
            $firstName === null ? null : UpperCaseName::of($firstName, 'first_name'),
            $birthDate === null ? null : CalendarDate::fromString($birthDate),
            null,
            null,
            null,
        ));
    }

    public static function child(string $firstName, string $lastName, string $birthDate): Child
    {
        return Child::of(CalendarDate::fromString($birthDate), UpperCaseName::of($lastName, 'last_name'), UpperCaseName::of($firstName, 'first_name'));
    }

    public static function period(string $start, string $end): DeclarationPeriod
    {
        return RecordSamples::period($start, $end, '2026-11-05');
    }

    public static function common(string $start, string $end): DeclarationCommon
    {
        return RecordSamples::common(100, 4, self::period($start, $end));
    }

    public static function childminder(): ChildminderDeclaration
    {
        return self::childminderFor(self::byNumber(), 'JEAN', '2026-10-01', '2026-10-31');
    }

    public static function childminderFor(DeclaredEmployee $employee, string $childFirstName, string $start, string $end): ChildminderDeclaration
    {
        return ChildminderDeclaration::of(
            self::common($start, $end),
            [ChildminderChild::of(self::child($childFirstName, 'DUPONT', '2015-01-12'), null, 3)],
            $employee,
            null,
            20,
            5,
            6,
            RecordSamples::amount('7'),
            RecordSamples::amount('8'),
        );
    }

    public static function homeCare(): HomeCareDeclaration
    {
        return self::homeCareFor(self::byNumber(), '2026-10-01', '2026-10-31');
    }

    public static function homeCareFor(DeclaredEmployee $employee, string $start, string $end): HomeCareDeclaration
    {
        return RecordSamples::homeCare($employee, self::common($start, $end));
    }

    /**
     * A simulator knowing the default employer.
     */
    public static function gateway(): SimulatedPajeGateway
    {
        $gateway = new SimulatedPajeGateway();
        $gateway->knowEmployer(self::employer());

        return $gateway;
    }

    /**
     * @param callable(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    public static function rejection(callable $action): UrssafRequestRejected
    {
        try {
            $action();
        } catch (UrssafRequestRejected $rejected) {
            return $rejected;
        }

        Assert::fail('Expected a rejection.');
    }
}
