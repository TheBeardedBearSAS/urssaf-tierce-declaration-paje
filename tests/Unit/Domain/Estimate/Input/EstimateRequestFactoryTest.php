<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Estimate\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\Input\EstimateRequestFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class EstimateRequestFactoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function childminder(): array
    {
        return [
            'employer_postal_code' => '94022',
            'employee_postal_code' => '75001',
            'net_monthly_salary' => '500.00',
            'children_count' => 2,
            'activity_days' => '20',
            'paid_leave_days' => '2.5',
            'majored_hours' => 3,
            'complementary_hours' => 4,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeCare(): array
    {
        return [
            'employer_postal_code' => '94022',
            'employee_postal_code' => '75001',
            'net_monthly_salary' => '500.00',
            'calculation_mode' => 'R',
            'hours' => 100,
            'overtime_hours_25' => 5,
            'overtime_hours_50' => 6,
            'youngest_child_age' => '2',
            'activity_supplement' => 'true',
        ];
    }

    public function testChildminderFromInput(): void
    {
        $request = (new EstimateRequestFactory())->childminder(self::childminder());

        static::assertSame('94022', $request->employerPostalCode()?->value());
        static::assertSame('75001', $request->employeePostalCode()?->value());
        static::assertSame('500.00', $request->netMonthlySalary()->toDecimalString());
        static::assertSame(2, $request->childrenCount());
        static::assertSame(20, $request->activityDays());
        static::assertSame('2.5', $request->paidLeaveDays()?->toDecimalString());
        static::assertSame(3, $request->majoredHours());
        static::assertSame(4, $request->complementaryHours());
        static::assertSame([], DomainAssertions::summary((new EstimateRequestFactory())->validate(DeclarationActivity::Childminder, self::childminder())));
    }

    public function testHomeCareFromInput(): void
    {
        $request = (new EstimateRequestFactory())->homeCare(self::homeCare());

        static::assertSame('94022', $request->employerPostalCode()?->value());
        static::assertSame('75001', $request->employeePostalCode()?->value());
        static::assertSame('500.00', $request->netMonthlySalary()->toDecimalString());
        static::assertSame('R', $request->calculationMode()->value());
        static::assertSame(100, $request->hours());
        static::assertSame(5, $request->overtimeHours25());
        static::assertSame(6, $request->overtimeHours50());
        static::assertSame('2', $request->youngestChildAge()->value());
        static::assertTrue($request->hasActivitySupplement());
        static::assertSame([], DomainAssertions::summary((new EstimateRequestFactory())->validate(DeclarationActivity::HomeCare, self::homeCare())));
    }

    public function testTheActivityChoosesTheRequest(): void
    {
        static::assertInstanceOf(ChildminderEstimateRequest::class, (new EstimateRequestFactory())->fromInput(DeclarationActivity::Childminder, self::childminder()));
        static::assertInstanceOf(HomeCareEstimateRequest::class, (new EstimateRequestFactory())->fromInput(DeclarationActivity::HomeCare, self::homeCare()));
    }

    public function testOptionalFieldsMayBeAbsent(): void
    {
        $childminder = (new EstimateRequestFactory())->childminder(['net_monthly_salary' => '1.00', 'children_count' => 1, 'activity_days' => 1]);
        $homeCare = (new EstimateRequestFactory())->homeCare(['activity_supplement' => false] + ['net_monthly_salary' => '1.00', 'calculation_mode' => 'F', 'hours' => 1, 'youngest_child_age' => '1']);

        static::assertNull($childminder->employerPostalCode());
        static::assertNull($childminder->employeePostalCode());
        static::assertNull($childminder->paidLeaveDays());
        static::assertNull($childminder->majoredHours());
        static::assertSame('F', $homeCare->calculationMode()->value());
        static::assertSame('1', $homeCare->youngestChildAge()->value());
        static::assertNull($homeCare->employerPostalCode());
        static::assertNull($homeCare->overtimeHours25());
        static::assertFalse($homeCare->hasActivitySupplement());
    }

    public function testEveryMissingFieldIsReported(): void
    {
        static::assertSame(
            [
                'error net_monthly_salary net_monthly_salary.required',
                'error children_count children_count.required',
                'error activity_days activity_days.required',
            ],
            DomainAssertions::summary((new EstimateRequestFactory())->validate(DeclarationActivity::Childminder, [])),
        );
        static::assertSame(
            [
                'error net_monthly_salary net_monthly_salary.required',
                'error calculation_mode calculation_mode.required',
                'error hours hours.required',
                'error youngest_child_age youngest_child_age.required',
                'error activity_supplement activity_supplement.required',
            ],
            DomainAssertions::summary((new EstimateRequestFactory())->validate(DeclarationActivity::HomeCare, [])),
        );
    }

    public function testEveryInvalidFieldIsReportedForAChildminder(): void
    {
        $result = (new EstimateRequestFactory())->validate(DeclarationActivity::Childminder, [
            'employer_postal_code' => '1',
            'employee_postal_code' => '123456',
            'net_monthly_salary' => '10000',
            'children_count' => 6,
            'activity_days' => 32,
            'paid_leave_days' => '99.5',
            'majored_hours' => 1000,
            'complementary_hours' => 1000,
        ]);

        static::assertSame(
            [
                'error employer_postal_code postal_code.too_short',
                'error employee_postal_code postal_code.too_long',
                'error net_monthly_salary net_monthly_salary.too_large',
                'error children_count children_count.out_of_range',
                'error activity_days activity_days.out_of_range',
                'error paid_leave_days paid_leave_days.out_of_range',
                'error majored_hours majored_hours.out_of_range',
                'error complementary_hours complementary_hours.out_of_range',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testEveryInvalidFieldIsReportedForHomeCare(): void
    {
        $result = (new EstimateRequestFactory())->validate(DeclarationActivity::HomeCare, [
            'employer_postal_code' => '1',
            'employee_postal_code' => '123456',
            'net_monthly_salary' => 'x',
            'calculation_mode' => 'RR',
            'hours' => 1001,
            'overtime_hours_25' => 36,
            'overtime_hours_50' => 41,
            'youngest_child_age' => '12',
            'activity_supplement' => 'maybe',
        ]);

        static::assertSame(
            [
                'error employer_postal_code postal_code.too_short',
                'error employee_postal_code postal_code.too_long',
                'error net_monthly_salary amount.invalid_format',
                'error calculation_mode calculation_mode.too_long',
                'error youngest_child_age youngest_child_age.too_long',
                'error activity_supplement activity_supplement.invalid_type',
                'error hours hours.out_of_range',
                'error overtime_hours_25 overtime_hours_25.out_of_range',
                'error overtime_hours_50 overtime_hours_50.out_of_range',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testAMissingFieldDoesNotHideTheRulesOfTheOthers(): void
    {
        static::assertSame(
            ['error net_monthly_salary net_monthly_salary.required', 'error activity_days activity_days.required', 'error children_count children_count.out_of_range'],
            DomainAssertions::summary((new EstimateRequestFactory())->validate(DeclarationActivity::Childminder, ['children_count' => 9])),
        );
        static::assertSame(
            ['error children_count children_count.required', 'error activity_days activity_days.out_of_range'],
            DomainAssertions::summary((new EstimateRequestFactory())->validate(DeclarationActivity::Childminder, ['net_monthly_salary' => '1.00', 'activity_days' => 0])),
        );
        static::assertSame(
            ['error net_monthly_salary net_monthly_salary.required', 'error calculation_mode calculation_mode.required', 'error hours hours.required', 'error youngest_child_age youngest_child_age.required', 'error activity_supplement activity_supplement.required', 'error overtime_hours_25 overtime_hours_25.out_of_range'],
            DomainAssertions::summary((new EstimateRequestFactory())->validate(DeclarationActivity::HomeCare, ['overtime_hours_25' => 36])),
        );
        static::assertSame(
            ['error hours hours.out_of_range'],
            DomainAssertions::summary((new EstimateRequestFactory())->validate(DeclarationActivity::HomeCare, ['hours' => 0] + self::homeCare())),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        foreach (['employer_postal_code' => '1', 'employee_postal_code' => '1', 'net_monthly_salary' => 'x', 'children_count' => 0, 'activity_days' => 0, 'paid_leave_days' => 'x', 'majored_hours' => -1, 'complementary_hours' => -1] as $key => $invalid) {
            static::assertNotSame([], (new EstimateRequestFactory())->validate(DeclarationActivity::Childminder, [$key => $invalid] + self::childminder())->errors(), $key);
        }
        foreach (['employer_postal_code' => '1', 'employee_postal_code' => '1', 'net_monthly_salary' => 'x', 'calculation_mode' => 'RR', 'hours' => 0, 'overtime_hours_25' => -1, 'overtime_hours_50' => -1, 'youngest_child_age' => '12', 'activity_supplement' => 'x'] as $key => $invalid) {
            static::assertNotSame([], (new EstimateRequestFactory())->validate(DeclarationActivity::HomeCare, [$key => $invalid] + self::homeCare())->errors(), $key);
        }
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error children_count children_count.out_of_range'],
            DeclarationSamples::violations(static fn(): mixed => (new EstimateRequestFactory())->childminder(['children_count' => 0] + self::childminder())),
        );
        static::assertSame(
            ['error hours hours.out_of_range'],
            DeclarationSamples::violations(static fn(): mixed => (new EstimateRequestFactory())->fromInput(DeclarationActivity::HomeCare, ['hours' => 0] + self::homeCare())),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        foreach (['validate', 'fromInput', 'childminder', 'homeCare'] as $method) {
            DomainAssertions::assertSensitiveParameters(EstimateRequestFactory::class, $method, ['input']);
        }
    }
}
