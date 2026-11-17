<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Estimate;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;

final class HomeCareEstimateRequestTest extends TestCase
{
    public function testExposesEveryField(): void
    {
        $employer = EstimateSamples::postalCode('94022');
        $employee = EstimateSamples::postalCode('75001');

        $request = HomeCareEstimateRequest::of(
            $employer,
            $employee,
            DeclarationSamples::amount('1500'),
            CalculationMode::flatRate(),
            120,
            3,
            4,
            YoungestChildAge::threeToSix(),
            true,
        );

        static::assertSame($employer, $request->employerPostalCode());
        static::assertSame($employee, $request->employeePostalCode());
        static::assertSame('1500.00', $request->netMonthlySalary()->toDecimalString());
        static::assertSame('F', $request->calculationMode()->value());
        static::assertSame(120, $request->hours());
        static::assertSame(3, $request->overtimeHours25());
        static::assertSame(4, $request->overtimeHours50());
        static::assertSame('2', $request->youngestChildAge()->value());
        static::assertTrue($request->hasActivitySupplement());
    }

    public function testPostalCodesAreNotDefaulted(): void
    {
        $request = self::request();

        static::assertNotInstanceOf(PostalCode::class, $request->employerPostalCode());
        static::assertNotInstanceOf(PostalCode::class, $request->employeePostalCode());
        static::assertNull($request->overtimeHours25());
        static::assertNull($request->overtimeHours50());
        static::assertFalse($request->hasActivitySupplement());
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        $low = self::request(Amount::fromCents(-1), 1, 0, 0);
        $high = self::request(Amount::fromCents(999_999), 1000, 35, 40);

        static::assertSame(1, $low->hours());
        static::assertSame(0, $low->overtimeHours25());
        static::assertSame(1000, $high->hours());
        static::assertSame(35, $high->overtimeHours25());
        static::assertSame(40, $high->overtimeHours50());
    }

    /**
     * @return iterable<string, array{string, string, Closure(): HomeCareEstimateRequest}>
     */
    public static function provideInvalidFields(): iterable
    {
        yield 'salary above 9999.99' => ['netMonthlySalary', 'net_monthly_salary.too_large', static fn(): HomeCareEstimateRequest => self::request(Amount::fromCents(1_000_000))];
        yield 'no hour' => ['hours', 'hours.out_of_range', static fn(): HomeCareEstimateRequest => self::request(null, 0)];
        yield 'hours above 1000' => ['hours', 'hours.out_of_range', static fn(): HomeCareEstimateRequest => self::request(null, 1001)];
        yield 'negative overtime 25' => ['overtimeHours25', 'overtime_hours_25.out_of_range', static fn(): HomeCareEstimateRequest => self::request(null, 100, -1)];
        yield 'overtime 25 above 35' => ['overtimeHours25', 'overtime_hours_25.out_of_range', static fn(): HomeCareEstimateRequest => self::request(null, 100, 36)];
        yield 'negative overtime 50' => ['overtimeHours50', 'overtime_hours_50.out_of_range', static fn(): HomeCareEstimateRequest => self::request(null, 100, null, -1)];
        yield 'overtime 50 above 40' => ['overtimeHours50', 'overtime_hours_50.out_of_range', static fn(): HomeCareEstimateRequest => self::request(null, 100, null, 41)];
    }

    /**
     * @param Closure(): HomeCareEstimateRequest $build
     */
    #[DataProvider('provideInvalidFields')]
    public function testEachBoundIsEnforced(string $path, string $code, Closure $build): void
    {
        static::assertSame([\sprintf('error %s %s', $path, $code)], DeclarationSamples::violations($build));
    }

    public function testEveryViolationIsReported(): void
    {
        static::assertSame(
            [
                'error netMonthlySalary net_monthly_salary.too_large',
                'error hours hours.out_of_range',
                'error overtimeHours25 overtime_hours_25.out_of_range',
                'error overtimeHours50 overtime_hours_50.out_of_range',
            ],
            DeclarationSamples::violations(static fn(): HomeCareEstimateRequest => self::request(Amount::fromCents(1_000_000), 0, 36, 41)),
        );
    }

    private static function request(
        ?Amount $salary = null,
        int $hours = 100,
        ?int $overtime25 = null,
        ?int $overtime50 = null,
    ): HomeCareEstimateRequest {
        return HomeCareEstimateRequest::of(
            null,
            null,
            $salary ?? DeclarationSamples::amount('500'),
            CalculationMode::real(),
            $hours,
            $overtime25,
            $overtime50,
            YoungestChildAge::underThree(),
            false,
        );
    }
}
