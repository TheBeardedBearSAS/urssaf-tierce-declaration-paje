<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;

/**
 * Valid stand-ins for the parts of a declaration that are missing or invalid in the input. A factory builds the
 * aggregate with them only to collect the violations of the fields that are present ("every violation at once", ADR 0000
 * item 4); a stand-in always comes with a violation of its own, so the object built with it is never returned.
 *
 * @internal
 */
final readonly class DeclarationPlaceholders
{
    public const DATE = '2000-01-01';
    public const HOURS = 1;
    public const ACTIVITY_DAYS = 1;
    private const EMPLOYEE_NUMBER = '00000000000000';
    private const NAME = 'A';

    public static function date(): CalendarDate
    {
        return CalendarDate::fromString(self::DATE);
    }

    public static function amount(): Amount
    {
        return Amount::fromCents(1);
    }

    public static function common(): DeclarationCommon
    {
        return DeclarationCommon::of(
            DeclarationPeriod::of(self::date(), self::date(), self::date()),
            self::HOURS,
            null,
            null,
            null,
            null,
            self::amount(),
            null,
        );
    }

    public static function employee(): DeclaredEmployee
    {
        return EmployeeByNumber::of(PajeEmployeeNumber::of(self::EMPLOYEE_NUMBER));
    }

    public static function child(): Child
    {
        return Child::of(self::date(), UpperCaseName::of(self::NAME, 'last_name'), UpperCaseName::of(self::NAME, 'first_name'));
    }

    public static function childminderChild(): ChildminderChild
    {
        return ChildminderChild::of(self::child(), null, null);
    }
}
