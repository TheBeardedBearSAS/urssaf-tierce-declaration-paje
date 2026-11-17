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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\DeclarationCommonFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class DeclarationCommonFactoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return [
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'payment_date' => '2026-11-05',
            'hours' => 120,
            'paid_leave_days' => '2.5',
            'net_hourly_rate' => '4.30',
            'specific_hours' => 3,
            'employee_advance' => '50.00',
            'net_monthly_salary' => '500.00',
            'mileage_allowance' => '12.50',
        ];
    }

    public function testFromInput(): void
    {
        $common = (new DeclarationCommonFactory())->fromInput(self::valid());

        static::assertTrue($common->period()->equals(DeclarationSamples::period()));
        static::assertSame(120, $common->hours());
        static::assertSame('2.5', $common->paidLeaveDays()?->toDecimalString());
        static::assertSame('4.30', $common->netHourlyRate()?->toDecimalString());
        static::assertSame(3, $common->specificHours());
        static::assertSame('50.00', $common->employeeAdvance()?->toDecimalString());
        static::assertSame('500.00', $common->netMonthlySalary()->toDecimalString());
        static::assertSame('12.50', $common->mileageAllowance()?->toDecimalString());
        static::assertSame([], DomainAssertions::summary((new DeclarationCommonFactory())->validate(self::valid())));
    }

    public function testConsoleStringsAreAccepted(): void
    {
        $common = (new DeclarationCommonFactory())->fromInput(['hours' => '120', 'specific_hours' => '3', 'net_monthly_salary' => 500] + self::valid());

        static::assertSame(120, $common->hours());
        static::assertSame(3, $common->specificHours());
        static::assertSame('500.00', $common->netMonthlySalary()->toDecimalString());
    }

    public function testOptionalFieldsMayBeAbsent(): void
    {
        $common = (new DeclarationCommonFactory())->fromInput([
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'payment_date' => '2026-11-05',
            'hours' => 1,
            'net_monthly_salary' => '0.01',
        ]);

        static::assertNull($common->paidLeaveDays());
        static::assertNull($common->netHourlyRate());
        static::assertNull($common->specificHours());
        static::assertNull($common->employeeAdvance());
        static::assertNull($common->mileageAllowance());
    }

    public function testEveryMissingFieldIsReported(): void
    {
        static::assertSame(
            [
                'error period_start period_start.required',
                'error period_end period_end.required',
                'error payment_date payment_date.required',
                'error hours hours.required',
                'error net_monthly_salary net_monthly_salary.required',
            ],
            DomainAssertions::summary((new DeclarationCommonFactory())->validate([])),
        );
    }

    public function testEveryInvalidFieldIsReported(): void
    {
        $result = (new DeclarationCommonFactory())->validate([
            'period_start' => 'x',
            'period_end' => '2026-02-30',
            'payment_date' => '05/11/2026',
            'hours' => 0,
            'paid_leave_days' => '2.3',
            'net_hourly_rate' => '1.234',
            'specific_hours' => 1001,
            'employee_advance' => 'abc',
            'net_monthly_salary' => '-1',
            'mileage_allowance' => '10000',
        ]);

        static::assertSame(
            [
                'error period_start calendar_date.invalid_format',
                'error period_end calendar_date.invalid_date',
                'error payment_date calendar_date.invalid_format',
                'error paid_leave_days half_days.not_multiple_of',
                'error net_hourly_rate amount.too_many_decimals',
                'error employee_advance amount.invalid_format',
                'error hours hours.out_of_range',
                'error specific_hours specific_hours.out_of_range',
                'error net_monthly_salary net_monthly_salary.not_positive',
                'error mileage_allowance mileage_allowance.too_large',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testTheRulesOfThePresentFieldsAreReportedBesideAMissingOne(): void
    {
        $result = (new DeclarationCommonFactory())->validate(['hours' => 0, 'specific_hours' => 1001, 'paid_leave_days' => '100']);

        static::assertSame(
            [
                'error period_start period_start.required',
                'error period_end period_end.required',
                'error payment_date payment_date.required',
                'error net_monthly_salary net_monthly_salary.required',
                'error hours hours.out_of_range',
                'error paid_leave_days paid_leave_days.out_of_range',
                'error specific_hours specific_hours.out_of_range',
            ],
            DomainAssertions::summary($result),
        );
        static::assertSame(
            ['error hours hours.required', 'error net_monthly_salary net_monthly_salary.not_positive'],
            DomainAssertions::summary((new DeclarationCommonFactory())->validate(['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'payment_date' => '2026-11-05', 'net_monthly_salary' => '0'])),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        foreach (['period_start' => 'x', 'period_end' => 'x', 'payment_date' => 'x', 'paid_leave_days' => 'x', 'net_hourly_rate' => 'x', 'employee_advance' => 'x', 'mileage_allowance' => 'x', 'hours' => 0, 'specific_hours' => -1, 'net_monthly_salary' => '0'] as $key => $invalid) {
            $result = (new DeclarationCommonFactory())->validate([$key => $invalid] + self::valid());
            static::assertCount(1, $result->errors(), $key);
        }
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error hours hours.invalid_type', 'error net_monthly_salary net_monthly_salary.invalid_type'],
            DeclarationSamples::violations(static fn(): mixed => (new DeclarationCommonFactory())->fromInput(['hours' => 'x', 'net_monthly_salary' => 1.5] + self::valid())),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(DeclarationCommonFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(DeclarationCommonFactory::class, 'fromInput', ['input']);
    }
}
