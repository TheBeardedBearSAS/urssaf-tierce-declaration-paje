<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;

/**
 * Domain objects equal to the official declaration examples (the `_valid` variants of tests/Fixtures/wire, see its
 * README). The comparison with a fixture lives in {@see WireBodyAssertions}.
 */
final class DeclarationWireSamples
{
    private function __construct() {}

    public static function amount(string $decimal): Amount
    {
        return DeclarationSamples::amount($decimal);
    }

    public static function child(): Child
    {
        return DeclarationSamples::child();
    }

    public static function employee(): DeclaredEmployee
    {
        return EmployeeByNumber::of(
            PajeEmployeeNumber::of('Y2926822480004'),
            CalendarDate::fromString('1980-01-30'),
        );
    }

    /**
     * The `inputDeclCommun` of the official examples: 2020-01-01 three times, 100 hours, 500 euros.
     */
    public static function common(): DeclarationCommon
    {
        $zero = self::amount('0');
        $date = CalendarDate::fromString('2020-01-01');

        return DeclarationCommon::of(
            DeclarationPeriod::of($date, $date, $date),
            100,
            HalfDays::fromHalfDays(0),
            $zero,
            0,
            $zero,
            self::amount('500'),
            $zero,
        );
    }

    /**
     * `declare_ama_request_valid`.
     */
    public static function declareChildminder(): ChildminderDeclaration
    {
        $zero = self::amount('0');

        return ChildminderDeclaration::of(
            self::common(),
            [ChildminderChild::of(self::child(), 10, 0)],
            self::employee(),
            ChildminderContractEnd::of($zero, $zero, $zero, $zero, null),
            10,
            0,
            0,
            $zero,
            $zero,
        );
    }

    /**
     * `predeclare_ama_request_valid`.
     */
    public static function preDeclareChildminder(): ChildminderDeclaration
    {
        $zero = self::amount('0');

        return ChildminderDeclaration::of(
            self::common(),
            [ChildminderChild::of(self::child(), 0, 0)],
            self::employee(),
            ChildminderContractEnd::of($zero, $zero, $zero, $zero, $zero),
            20,
            0,
            0,
            $zero,
            $zero,
        );
    }

    /**
     * `declare_ged_request_valid` and `predeclare_ged_request_valid`.
     */
    public static function homeCare(): HomeCareDeclaration
    {
        $zero = self::amount('0');

        return HomeCareDeclaration::of(
            CalculationMode::real(),
            self::common(),
            [self::child()],
            self::employee(),
            HomeCareContractEnd::of($zero, $zero, $zero, $zero, $zero),
            $zero,
            0,
            0,
        );
    }
}
