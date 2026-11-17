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

use Closure;
use LogicException;
use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * Builders of valid objects (official example values or obviously fictitious ones) shared by the declaration and
 * estimate tests.
 */
final class DeclarationSamples
{
    public static function date(string $date = '2026-10-01'): CalendarDate
    {
        return CalendarDate::fromString($date);
    }

    public static function amount(string $decimal): Amount
    {
        return Amount::fromDecimalString($decimal);
    }

    public static function halfDays(int $halfDays): HalfDays
    {
        try {
            return HalfDays::fromHalfDays($halfDays);
        } catch (InvalidValue $invalid) {
            throw new LogicException('Invalid sample half-days.', 0, $invalid);
        }
    }

    public static function period(): DeclarationPeriod
    {
        return DeclarationPeriod::of(self::date('2026-10-01'), self::date('2026-10-31'), self::date('2026-11-05'));
    }

    public static function child(): Child
    {
        return Child::of(
            self::date('2015-01-12'),
            UpperCaseName::of("L'HOMME", 'child_last_name'),
            UpperCaseName::of('JEAN-MICHEL', 'child_first_name'),
        );
    }

    public static function childminderChild(?int $disabled = null, ?int $consecutive = null): ChildminderChild
    {
        return ChildminderChild::of(self::child(), $disabled, $consecutive);
    }

    public static function employee(): DeclaredEmployee
    {
        return EmployeeByNumber::of(PajeEmployeeNumber::of('Y2926822480004'));
    }

    /**
     * @mago-expect lint:excessive-parameter-list
     */
    public static function common(
        int $hours = 100,
        ?HalfDays $paidLeaveDays = null,
        ?Amount $netHourlyRate = null,
        ?int $specificHours = null,
        ?Amount $employeeAdvance = null,
        ?Amount $netMonthlySalary = null,
        ?Amount $mileageAllowance = null,
    ): DeclarationCommon {
        return DeclarationCommon::of(
            self::period(),
            $hours,
            $paidLeaveDays,
            $netHourlyRate,
            $specificHours,
            $employeeAdvance,
            $netMonthlySalary ?? self::amount('500'),
            $mileageAllowance,
        );
    }

    /**
     * Runs the action, expects a {@see ValidationFailed} and returns its violations as "error <path> <code>".
     *
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     *
     * @return list<string>
     */
    public static function violations(Closure $action): array
    {
        try {
            $action();
        } catch (ValidationFailed $failed) {
            return DomainAssertions::summary($failed->result());
        }
        Assert::fail('Expected a ValidationFailed.');
    }
}
