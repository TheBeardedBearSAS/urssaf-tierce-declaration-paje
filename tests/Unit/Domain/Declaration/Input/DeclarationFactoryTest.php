<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\DeclarationPlaceholders;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class DeclarationFactoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function common(): array
    {
        return [
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'payment_date' => '2026-11-05',
            'hours' => 100,
            'net_monthly_salary' => '500.00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function child(): array
    {
        return ['birth_date' => '2015-01-12', 'last_name' => "L'HOMME", 'first_name' => 'JEAN-MICHEL'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function childminder(): array
    {
        return [
            'common' => self::common(),
            'employee' => ['employee_number' => 'Y2926822480004'],
            'children' => [self::child() + ['disabled_child_care_days' => 4, 'consecutive_24h_days' => 2]],
            'contract_end' => ['notice_indemnity' => '10.00'],
            'activity_days' => 20,
            'majored_hours' => 5,
            'complementary_hours' => 6,
            'maintenance_allowance' => '30.00',
            'meal_allowance' => '40.00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeCare(): array
    {
        return [
            'calculation_mode' => 'R',
            'common' => self::common(),
            'employee' => ['nir' => '280017501234567'],
            'children' => [self::child()],
            'contract_end' => ['dismissal_indemnity' => '10.00'],
            'transport_costs' => '20.00',
            'overtime_hours_25' => 7,
            'overtime_hours_50' => 8,
        ];
    }

    public function testChildminderFromInput(): void
    {
        $declaration = (new DeclarationFactory())->childminder(self::childminder());

        static::assertSame(100, $declaration->common()->hours());
        $employee = $declaration->employee();
        static::assertInstanceOf(EmployeeByNumber::class, $employee);
        static::assertSame('Y2926822480004', $employee->number()->value());
        static::assertCount(1, $declaration->children());
        static::assertTrue($declaration->children()[0]->child()->equals(DeclarationSamples::child()));
        static::assertSame(4, $declaration->children()[0]->disabledChildCareDays());
        static::assertSame('10.00', $declaration->contractEnd()?->noticeIndemnity()?->toDecimalString());
        static::assertSame(20, $declaration->activityDays());
        static::assertSame(5, $declaration->majoredHours());
        static::assertSame(6, $declaration->complementaryHours());
        static::assertSame('30.00', $declaration->maintenanceAllowance()?->toDecimalString());
        static::assertSame('40.00', $declaration->mealAllowance()?->toDecimalString());
        static::assertSame([], DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::Childminder, self::childminder())));
    }

    public function testHomeCareFromInput(): void
    {
        $declaration = (new DeclarationFactory())->homeCare(self::homeCare());

        static::assertSame('R', $declaration->calculationMode()->value());
        static::assertSame(100, $declaration->common()->hours());
        $employee = $declaration->employee();
        static::assertInstanceOf(EmployeeByNir::class, $employee);
        static::assertSame('280017501234567', $employee->nir()->value());
        static::assertCount(1, $declaration->children());
        static::assertSame('10.00', $declaration->contractEnd()?->dismissalIndemnity()?->toDecimalString());
        static::assertSame('20.00', $declaration->transportCosts()?->toDecimalString());
        static::assertSame(7, $declaration->overtimeHours25());
        static::assertSame(8, $declaration->overtimeHours50());
        static::assertSame([], DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::HomeCare, self::homeCare())));
    }

    public function testTheActivityChoosesTheDeclaration(): void
    {
        static::assertInstanceOf(ChildminderDeclaration::class, (new DeclarationFactory())->fromInput(DeclarationActivity::Childminder, self::childminder()));
        static::assertInstanceOf(HomeCareDeclaration::class, (new DeclarationFactory())->fromInput(DeclarationActivity::HomeCare, self::homeCare()));
    }

    public function testOptionalPartsMayBeAbsent(): void
    {
        $minimal = ['common' => self::common(), 'employee' => ['nir' => '280017501234567'], 'children' => [self::child()]];

        $childminder = (new DeclarationFactory())->childminder($minimal + ['activity_days' => 1]);
        $homeCare = (new DeclarationFactory())->homeCare($minimal + ['calculation_mode' => 'F']);

        static::assertNull($childminder->contractEnd());
        static::assertNull($childminder->majoredHours());
        static::assertSame('F', $homeCare->calculationMode()->value());
        static::assertNull($homeCare->contractEnd());
        static::assertNull($homeCare->transportCosts());
    }

    public function testEveryMissingPartIsReportedForAChildminder(): void
    {
        static::assertSame(
            [
                'error common.period_start period_start.required',
                'error common.period_end period_end.required',
                'error common.payment_date payment_date.required',
                'error common.hours hours.required',
                'error common.net_monthly_salary net_monthly_salary.required',
                'error employee employee_identification.empty',
                'error activity_days activity_days.required',
                'error children children.empty',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::Childminder, [])),
        );
    }

    public function testEveryMissingPartIsReportedForHomeCare(): void
    {
        static::assertSame(
            [
                'error calculation_mode calculation_mode.required',
                'error common.period_start period_start.required',
                'error common.period_end period_end.required',
                'error common.payment_date payment_date.required',
                'error common.hours hours.required',
                'error common.net_monthly_salary net_monthly_salary.required',
                'error employee employee_identification.empty',
                'error children children.empty',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::HomeCare, [])),
        );
    }

    public function testEveryInvalidPartIsReportedWithNestedPathsForAChildminder(): void
    {
        $input = [
            'common' => ['hours' => 0, 'paid_leave_days' => '100', 'specific_hours' => 1001, 'net_monthly_salary' => '0'] + self::common(),
            'employee' => ['nir' => '1'],
            'children' => [
                ['consecutive_24h_days' => 'x'],
                self::child() + ['disabled_child_care_days' => 3_000_000_000],
            ],
            'contract_end' => ['notice_indemnity' => '99999'],
            'activity_days' => 100,
            'majored_hours' => 1000,
            'complementary_hours' => 1000,
            'maintenance_allowance' => '-1',
            'meal_allowance' => '10000',
        ];

        static::assertSame(
            [
                'error common.hours hours.out_of_range',
                'error common.paid_leave_days paid_leave_days.out_of_range',
                'error common.specific_hours specific_hours.out_of_range',
                'error common.net_monthly_salary net_monthly_salary.not_positive',
                'error employee.nir nir.too_short',
                'error children[0].birth_date birth_date.required',
                'error children[0].last_name last_name.required',
                'error children[0].first_name first_name.required',
                'error children[0].consecutive_24h_days consecutive_24h_days.invalid_type',
                'error children[1].disabled_child_care_days disabled_child_care_days.out_of_range',
                'error contract_end.notice_indemnity notice_indemnity.too_large',
                'error activity_days activity_days.out_of_range',
                'error majored_hours majored_hours.out_of_range',
                'error complementary_hours complementary_hours.out_of_range',
                'error maintenance_allowance maintenance_allowance.negative',
                'error meal_allowance meal_allowance.too_large',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::Childminder, $input)),
        );
    }

    public function testEveryInvalidPartIsReportedWithNestedPathsForHomeCare(): void
    {
        $input = [
            'calculation_mode' => 'RR',
            'common' => ['hours' => 'x'] + self::common(),
            'employee' => ['employee_number' => '1'],
            'children' => [self::child(), ['birth_date' => 'x', 'last_name' => 'A', 'first_name' => 'B'], 'x'],
            'contract_end' => ['retirement_indemnity' => 'x'],
            'transport_costs' => '10000',
            'overtime_hours_25' => 36,
            'overtime_hours_50' => 41,
        ];

        static::assertSame(
            [
                'error calculation_mode calculation_mode.too_long',
                'error common.hours hours.invalid_type',
                'error employee.employee_number employee_number.too_short',
                'error children[1].birth_date calendar_date.invalid_format',
                'error children[2] children.invalid_type',
                'error contract_end.retirement_indemnity amount.invalid_format',
                'error transport_costs transport_costs.too_large',
                'error overtime_hours_25 overtime_hours_25.out_of_range',
                'error overtime_hours_50 overtime_hours_50.out_of_range',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::HomeCare, $input)),
        );
    }

    public function testAnInvalidChildDoesNotMakeTheListLookEmpty(): void
    {
        $onlyInvalid = ['common' => self::common(), 'employee' => ['nir' => '280017501234567'], 'children' => [['birth_date' => 'x']], 'activity_days' => 1, 'calculation_mode' => 'R'];

        static::assertSame(
            ['error children[0].birth_date calendar_date.invalid_format', 'error children[0].last_name last_name.required', 'error children[0].first_name first_name.required'],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::Childminder, $onlyInvalid)),
        );
        static::assertSame(
            ['error children[0].birth_date calendar_date.invalid_format', 'error children[0].last_name last_name.required', 'error children[0].first_name first_name.required'],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::HomeCare, $onlyInvalid)),
        );
        static::assertSame(
            ['error children children.empty'],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::HomeCare, ['children' => []] + $onlyInvalid)),
        );
        static::assertSame(
            ['error children children.invalid_type'],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::HomeCare, ['children' => 'x'] + $onlyInvalid)),
        );
    }

    public function testAMissingPartDoesNotHideTheRulesOfTheOthers(): void
    {
        static::assertSame(
            ['error employee employee_identification.empty', 'error activity_days activity_days.out_of_range', 'error meal_allowance meal_allowance.too_large'],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::Childminder, ['common' => self::common(), 'children' => [self::child()], 'activity_days' => 0, 'meal_allowance' => '10000'])),
        );
        static::assertSame(
            ['error common.hours hours.required', 'error overtime_hours_25 overtime_hours_25.out_of_range'],
            DomainAssertions::summary((new DeclarationFactory())->validate(DeclarationActivity::HomeCare, ['calculation_mode' => 'R', 'common' => ['hours' => null] + self::common(), 'employee' => ['nir' => '280017501234567'], 'children' => [self::child()], 'overtime_hours_25' => 99])),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        foreach (['common' => ['hours' => 0] + self::common(), 'employee' => ['nir' => '1'], 'children' => [['x' => 1]], 'contract_end' => ['notice_indemnity' => 'x'], 'activity_days' => 0, 'majored_hours' => 1000, 'complementary_hours' => 1000, 'maintenance_allowance' => '-1', 'meal_allowance' => '10000'] as $key => $invalid) {
            $result = (new DeclarationFactory())->validate(DeclarationActivity::Childminder, [$key => $invalid] + self::childminder());
            static::assertNotSame([], $result->errors(), $key);
        }
        foreach (['calculation_mode' => 'RR', 'common' => ['hours' => 0] + self::common(), 'employee' => ['nir' => '1'], 'children' => [['x' => 1]], 'contract_end' => ['dismissal_indemnity' => 'x'], 'transport_costs' => '10000', 'overtime_hours_25' => 36, 'overtime_hours_50' => 41] as $key => $invalid) {
            $result = (new DeclarationFactory())->validate(DeclarationActivity::HomeCare, [$key => $invalid] + self::homeCare());
            static::assertNotSame([], $result->errors(), $key);
        }
    }

    public function testFromInputThrowsEveryViolation(): void
    {
        static::assertSame(
            ['error activity_days activity_days.required'],
            DeclarationSamples::violations(static fn(): mixed => (new DeclarationFactory())->childminder(['activity_days' => null] + self::childminder())),
        );
        static::assertSame(
            ['error calculation_mode calculation_mode.required'],
            DeclarationSamples::violations(static fn(): mixed => (new DeclarationFactory())->fromInput(DeclarationActivity::HomeCare, ['calculation_mode' => null] + self::homeCare())),
        );
    }

    public function testThePlaceholdersAreValid(): void
    {
        static::assertSame(DeclarationPlaceholders::DATE, DeclarationPlaceholders::date()->toString());
        static::assertSame(DeclarationPlaceholders::HOURS, DeclarationPlaceholders::common()->hours());
        static::assertSame(1, DeclarationPlaceholders::amount()->cents());
        static::assertSame('A', DeclarationPlaceholders::child()->lastName()->value());
        static::assertSame('A', DeclarationPlaceholders::childminderChild()->child()->firstName()->value());
        $employee = DeclarationPlaceholders::employee();
        static::assertInstanceOf(EmployeeByNumber::class, $employee);
        static::assertSame('00000000000000', $employee->number()->value());
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(DeclarationFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(DeclarationFactory::class, 'fromInput', ['input']);
        DomainAssertions::assertSensitiveParameters(DeclarationFactory::class, 'childminder', ['input']);
        DomainAssertions::assertSensitiveParameters(DeclarationFactory::class, 'homeCare', ['input']);
    }
}
