<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\DeclaredEmployeeFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class DeclaredEmployeeFactoryTest extends TestCase
{
    public function testByNumber(): void
    {
        $employee = (new DeclaredEmployeeFactory())->fromInput(['employee_number' => 'Y2926822480004', 'birth_date' => '1990-04-05']);

        static::assertInstanceOf(EmployeeByNumber::class, $employee);
        static::assertSame('Y2926822480004', $employee->number()->value());
        static::assertSame('1990-04-05', $employee->birthDate()?->toString());
    }

    public function testByNir(): void
    {
        $employee = (new DeclaredEmployeeFactory())->fromInput(['nir' => '280017501234567']);

        static::assertInstanceOf(EmployeeByNir::class, $employee);
        static::assertSame('280017501234567', $employee->nir()->value());
        static::assertNull($employee->birthDate());
    }

    public function testTheNumberHasPriorityOverTheNir(): void
    {
        $employee = (new DeclaredEmployeeFactory())->fromInput(['employee_number' => 'Y2926822480004', 'nir' => '280017501234567']);

        static::assertInstanceOf(EmployeeByNumber::class, $employee);
    }

    public function testWithoutCriterionTheIdentificationIsEmpty(): void
    {
        static::assertSame(['error  employee_identification.empty'], DomainAssertions::summary((new DeclaredEmployeeFactory())->validate([])));
        static::assertSame(
            ['error  employee_identification.empty'],
            DomainAssertions::summary((new DeclaredEmployeeFactory())->validate(['employee_number' => '', 'nir' => null, 'birth_date' => '1990-04-05'])),
        );
    }

    public function testEveryGivenCriterionIsValidatedEvenWhenAHigherOneIsGiven(): void
    {
        static::assertSame(
            ['error birth_date calendar_date.invalid_format', 'error employee_number employee_number.too_short', 'error nir nir.too_short'],
            DomainAssertions::summary((new DeclaredEmployeeFactory())->validate(['employee_number' => '1', 'nir' => '2', 'birth_date' => 'x'])),
        );
        static::assertSame(
            ['error nir nir.too_short'],
            DomainAssertions::summary((new DeclaredEmployeeFactory())->validate(['employee_number' => 'Y2926822480004', 'nir' => '2'])),
        );
        static::assertSame(
            ['error employee_number employee_number.too_long'],
            DomainAssertions::summary((new DeclaredEmployeeFactory())->validate(['employee_number' => 'Y29268224800041', 'nir' => '280017501234567'])),
        );
    }

    public function testAnInvalidBirthDateDoesNotHideAMissingCriterion(): void
    {
        static::assertSame(
            ['error birth_date calendar_date.invalid_format', 'error  employee_identification.empty'],
            DomainAssertions::summary((new DeclaredEmployeeFactory())->validate(['birth_date' => 'x'])),
        );
    }

    public function testAnInvalidBirthDateFailsAValidCriterion(): void
    {
        static::assertSame(
            ['error birth_date calendar_date.invalid_date'],
            DomainAssertions::summary((new DeclaredEmployeeFactory())->validate(['employee_number' => 'Y2926822480004', 'birth_date' => '1990-02-31'])),
        );
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error  employee_identification.empty'],
            DeclarationSamples::violations(static fn(): mixed => (new DeclaredEmployeeFactory())->fromInput([])),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(DeclaredEmployeeFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(DeclaredEmployeeFactory::class, 'fromInput', ['input']);
        DomainAssertions::assertSensitiveParameters(DeclaredEmployeeFactory::class, 'parseCriteria', ['birthDate']);
    }
}
