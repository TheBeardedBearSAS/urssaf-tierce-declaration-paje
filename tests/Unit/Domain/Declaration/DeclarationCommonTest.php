<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;

final class DeclarationCommonTest extends TestCase
{
    public function testExposesEveryField(): void
    {
        $common = DeclarationCommon::of(
            DeclarationSamples::period(),
            100,
            HalfDays::fromHalfDays(3),
            DeclarationSamples::amount('5.5'),
            10,
            DeclarationSamples::amount('20'),
            DeclarationSamples::amount('500'),
            DeclarationSamples::amount('12.34'),
        );

        static::assertTrue($common->period()->equals(DeclarationSamples::period()));
        static::assertSame(100, $common->hours());
        static::assertSame('1.5', $common->paidLeaveDays()?->toDecimalString());
        static::assertSame('5.50', $common->netHourlyRate()?->toDecimalString());
        static::assertSame(10, $common->specificHours());
        static::assertSame('20.00', $common->employeeAdvance()?->toDecimalString());
        static::assertSame('500.00', $common->netMonthlySalary()->toDecimalString());
        static::assertSame('12.34', $common->mileageAllowance()?->toDecimalString());
    }

    public function testOptionalFieldsMayBeMissing(): void
    {
        $common = DeclarationSamples::common();

        static::assertNotInstanceOf(HalfDays::class, $common->paidLeaveDays());
        static::assertNull($common->netHourlyRate());
        static::assertNull($common->specificHours());
        static::assertNull($common->employeeAdvance());
        static::assertNull($common->mileageAllowance());
    }

    public function testHoursAreBoundedByInt32Only(): void
    {
        static::assertSame(2_147_483_647, DeclarationSamples::common(2_147_483_647)->hours());
        static::assertSame(1, DeclarationSamples::common(1)->hours());
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        $max = DeclarationSamples::amount('9999.99');
        $common = DeclarationSamples::common(100, HalfDays::fromHalfDays(198), $max, 1000, $max, $max, $max);

        static::assertSame('99', $common->paidLeaveDays()?->toDecimalString());
        static::assertSame(1000, $common->specificHours());
        static::assertSame('9999.99', $common->netMonthlySalary()->toDecimalString());
    }

    public function testSpecificHoursMayBeZero(): void
    {
        static::assertSame(0, DeclarationSamples::common(100, null, null, 0)->specificHours());
    }

    public function testSmallestPositiveSalaryIsAccepted(): void
    {
        static::assertSame('0.01', DeclarationSamples::common(100, null, null, null, null, DeclarationSamples::amount('0.01'))
            ->netMonthlySalary()
            ->toDecimalString());
    }

    public function testNegativeOptionalAmountsHaveNoMinimum(): void
    {
        $negative = DeclarationSamples::amount('-1');
        $common = DeclarationSamples::common(100, null, $negative, null, $negative, null, $negative);

        static::assertSame('-1.00', $common->netHourlyRate()?->toDecimalString());
    }

    /**
     * @return iterable<string, array{string, string, Closure(): DeclarationCommon}>
     */
    public static function provideInvalidFields(): iterable
    {
        $tooBig = static fn(): Amount => Amount::fromCents(1_000_000);

        yield 'zero hours' => ['hours', 'hours.out_of_range', static fn(): DeclarationCommon => DeclarationSamples::common(0)];
        yield 'hours above int32' => ['hours', 'hours.out_of_range', static fn(): DeclarationCommon => DeclarationSamples::common(2_147_483_648)];
        yield 'negative hours' => ['hours', 'hours.out_of_range', static fn(): DeclarationCommon => DeclarationSamples::common(-1)];
        yield 'paid leave above 99' => ['paidLeaveDays', 'paid_leave_days.out_of_range', static fn(): DeclarationCommon => DeclarationSamples::common(100, DeclarationSamples::halfDays(199))];
        yield 'hourly rate above 9999.99' => ['netHourlyRate', 'net_hourly_rate.too_large', static fn(): DeclarationCommon => DeclarationSamples::common(100, null, $tooBig())];
        yield 'specific hours above 1000' => ['specificHours', 'specific_hours.out_of_range', static fn(): DeclarationCommon => DeclarationSamples::common(100, null, null, 1001)];
        yield 'negative specific hours' => ['specificHours', 'specific_hours.out_of_range', static fn(): DeclarationCommon => DeclarationSamples::common(100, null, null, -1)];
        yield 'advance above 9999.99' => ['employeeAdvance', 'employee_advance.too_large', static fn(): DeclarationCommon => DeclarationSamples::common(100, null, null, null, $tooBig())];
        yield 'zero salary' => ['netMonthlySalary', 'net_monthly_salary.not_positive', static fn(): DeclarationCommon => DeclarationSamples::common(100, null, null, null, null, Amount::fromCents(0))];
        yield 'negative salary' => ['netMonthlySalary', 'net_monthly_salary.not_positive', static fn(): DeclarationCommon => DeclarationSamples::common(100, null, null, null, null, Amount::fromCents(-1))];
        yield 'salary above 9999.99' => ['netMonthlySalary', 'net_monthly_salary.too_large', static fn(): DeclarationCommon => DeclarationSamples::common(100, null, null, null, null, $tooBig())];
        yield 'mileage above 9999.99' => ['mileageAllowance', 'mileage_allowance.too_large', static fn(): DeclarationCommon => DeclarationSamples::common(100, null, null, null, null, null, $tooBig())];
    }

    /**
     * @param Closure(): DeclarationCommon $build
     */
    #[DataProvider('provideInvalidFields')]
    public function testEachBoundIsEnforced(string $path, string $code, Closure $build): void
    {
        static::assertSame([\sprintf('error %s %s', $path, $code)], DeclarationSamples::violations($build));
    }

    public function testEveryViolationIsReported(): void
    {
        $tooBig = Amount::fromCents(1_000_000);

        static::assertSame(
            [
                'error hours hours.out_of_range',
                'error paidLeaveDays paid_leave_days.out_of_range',
                'error netHourlyRate net_hourly_rate.too_large',
                'error specificHours specific_hours.out_of_range',
                'error employeeAdvance employee_advance.too_large',
                'error netMonthlySalary net_monthly_salary.not_positive',
                'error mileageAllowance mileage_allowance.too_large',
            ],
            DeclarationSamples::violations(static fn(): DeclarationCommon => DeclarationSamples::common(
                0,
                HalfDays::fromHalfDays(199),
                $tooBig,
                1001,
                $tooBig,
                DeclarationSamples::amount('0'),
                $tooBig,
            )),
        );
    }
}
