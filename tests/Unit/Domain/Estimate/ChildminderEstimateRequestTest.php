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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;

final class ChildminderEstimateRequestTest extends TestCase
{
    public function testExposesEveryField(): void
    {
        $employer = EstimateSamples::postalCode('94022');
        $employee = EstimateSamples::postalCode('75001');

        $request = ChildminderEstimateRequest::of(
            $employer,
            $employee,
            DeclarationSamples::amount('1500'),
            2,
            20,
            HalfDays::fromHalfDays(3),
            4,
            5,
        );

        static::assertSame($employer, $request->employerPostalCode());
        static::assertSame($employee, $request->employeePostalCode());
        static::assertSame('1500.00', $request->netMonthlySalary()->toDecimalString());
        static::assertSame(2, $request->childrenCount());
        static::assertSame(20, $request->activityDays());
        static::assertSame('1.5', $request->paidLeaveDays()?->toDecimalString());
        static::assertSame(4, $request->majoredHours());
        static::assertSame(5, $request->complementaryHours());
    }

    public function testPostalCodesAreNotDefaulted(): void
    {
        $request = self::request();

        static::assertNotInstanceOf(PostalCode::class, $request->employerPostalCode());
        static::assertNotInstanceOf(PostalCode::class, $request->employeePostalCode());
        static::assertNotInstanceOf(HalfDays::class, $request->paidLeaveDays());
        static::assertNull($request->majoredHours());
        static::assertNull($request->complementaryHours());
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        $low = self::request(Amount::fromCents(-1), 1, 1, null, 0, 0);
        $high = self::request(Amount::fromCents(999_999), 5, 31, HalfDays::fromHalfDays(198), 999, 999);

        static::assertSame(1, $low->childrenCount());
        static::assertSame(1, $low->activityDays());
        static::assertSame(0, $low->majoredHours());
        static::assertSame(5, $high->childrenCount());
        static::assertSame(31, $high->activityDays());
        static::assertSame('99', $high->paidLeaveDays()?->toDecimalString());
        static::assertSame(999, $high->majoredHours());
        static::assertSame(999, $high->complementaryHours());
    }

    /**
     * @return iterable<string, array{string, string, Closure(): ChildminderEstimateRequest}>
     */
    public static function provideInvalidFields(): iterable
    {
        yield 'salary above 9999.99' => ['netMonthlySalary', 'net_monthly_salary.too_large', static fn(): ChildminderEstimateRequest => self::request(Amount::fromCents(1_000_000))];
        yield 'no child' => ['childrenCount', 'children_count.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 0)];
        yield 'six children' => ['childrenCount', 'children_count.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 6)];
        yield 'no activity day' => ['activityDays', 'activity_days.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 1, 0)];
        yield '32 activity days' => ['activityDays', 'activity_days.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 1, 32)];
        yield 'paid leave above 99' => ['paidLeaveDays', 'paid_leave_days.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 1, 10, DeclarationSamples::halfDays(199))];
        yield 'negative majored hours' => ['majoredHours', 'majored_hours.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 1, 10, null, -1)];
        yield 'majored hours above 999' => ['majoredHours', 'majored_hours.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 1, 10, null, 1000)];
        yield 'negative complementary hours' => ['complementaryHours', 'complementary_hours.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 1, 10, null, null, -1)];
        yield 'complementary hours above 999' => ['complementaryHours', 'complementary_hours.out_of_range', static fn(): ChildminderEstimateRequest => self::request(null, 1, 10, null, null, 1000)];
    }

    /**
     * @param Closure(): ChildminderEstimateRequest $build
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
                'error childrenCount children_count.out_of_range',
                'error activityDays activity_days.out_of_range',
                'error paidLeaveDays paid_leave_days.out_of_range',
                'error majoredHours majored_hours.out_of_range',
                'error complementaryHours complementary_hours.out_of_range',
            ],
            DeclarationSamples::violations(static fn(): ChildminderEstimateRequest => self::request(Amount::fromCents(1_000_000), 0, 0, HalfDays::fromHalfDays(199), 1000, 1000)),
        );
    }

    /**
     * @mago-expect lint:excessive-parameter-list
     */
    private static function request(
        ?Amount $salary = null,
        int $children = 1,
        int $days = 10,
        ?HalfDays $paidLeave = null,
        ?int $majored = null,
        ?int $complementary = null,
    ): ChildminderEstimateRequest {
        return ChildminderEstimateRequest::of(
            null,
            null,
            $salary ?? DeclarationSamples::amount('500'),
            $children,
            $days,
            $paidLeave,
            $majored,
            $complementary,
        );
    }
}
