<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;

final class ChildminderDeclarationTest extends TestCase
{
    public function testExposesEveryField(): void
    {
        $common = DeclarationSamples::common();
        $child = DeclarationSamples::childminderChild(10);
        $employee = DeclarationSamples::employee();
        $contractEnd = ChildminderContractEnd::of(null, null, null, null, null);

        $declaration = ChildminderDeclaration::of(
            $common,
            [$child],
            $employee,
            $contractEnd,
            10,
            5,
            6,
            DeclarationSamples::amount('7.5'),
            DeclarationSamples::amount('8'),
        );

        static::assertSame($common, $declaration->common());
        static::assertSame([$child], $declaration->children());
        static::assertSame($employee, $declaration->employee());
        static::assertSame($contractEnd, $declaration->contractEnd());
        static::assertSame(10, $declaration->activityDays());
        static::assertSame(5, $declaration->majoredHours());
        static::assertSame(6, $declaration->complementaryHours());
        static::assertSame('7.50', $declaration->maintenanceAllowance()?->toDecimalString());
        static::assertSame('8.00', $declaration->mealAllowance()?->toDecimalString());
        static::assertSame(DeclarationActivity::Childminder, $declaration->activityType());
    }

    public function testOptionalFieldsMayBeMissing(): void
    {
        $declaration = self::declaration();

        static::assertNotInstanceOf(ChildminderContractEnd::class, $declaration->contractEnd());
        static::assertNull($declaration->majoredHours());
        static::assertNull($declaration->complementaryHours());
        static::assertNull($declaration->maintenanceAllowance());
        static::assertNull($declaration->mealAllowance());
    }

    public function testSeveralChildrenAreNotRefusedLocally(): void
    {
        $children = [DeclarationSamples::childminderChild(), DeclarationSamples::childminderChild()];

        static::assertCount(2, self::declaration($children)->children());
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        $max = DeclarationSamples::amount('9999.99');
        $low = self::declaration(null, 1, 0, 0, DeclarationSamples::amount('0'));
        $high = self::declaration(null, 99, 999, 999, $max, $max);

        static::assertSame(1, $low->activityDays());
        static::assertSame(0, $low->majoredHours());
        static::assertSame(99, $high->activityDays());
        static::assertSame(999, $high->majoredHours());
        static::assertSame(999, $high->complementaryHours());
        static::assertSame('9999.99', $high->mealAllowance()?->toDecimalString());
    }

    public function testMealAllowanceHasNoMinimum(): void
    {
        static::assertSame('-1.00', self::declaration(null, 10, null, null, null, DeclarationSamples::amount('-1'))->mealAllowance()?->toDecimalString());
    }

    /**
     * @return iterable<string, array{string, string, Closure(): ChildminderDeclaration}>
     */
    public static function provideInvalidFields(): iterable
    {
        $tooBig = static fn(): Amount => Amount::fromCents(1_000_000);

        yield 'no child' => ['children', 'children.empty', static fn(): ChildminderDeclaration => self::declaration([])];
        yield 'zero activity days' => ['activityDays', 'activity_days.out_of_range', static fn(): ChildminderDeclaration => self::declaration(null, 0)];
        yield 'activity days above 99' => ['activityDays', 'activity_days.out_of_range', static fn(): ChildminderDeclaration => self::declaration(null, 100)];
        yield 'negative majored hours' => ['majoredHours', 'majored_hours.out_of_range', static fn(): ChildminderDeclaration => self::declaration(null, 10, -1)];
        yield 'majored hours above 999' => ['majoredHours', 'majored_hours.out_of_range', static fn(): ChildminderDeclaration => self::declaration(null, 10, 1000)];
        yield 'negative complementary hours' => ['complementaryHours', 'complementary_hours.out_of_range', static fn(): ChildminderDeclaration => self::declaration(null, 10, null, -1)];
        yield 'complementary hours above 999' => ['complementaryHours', 'complementary_hours.out_of_range', static fn(): ChildminderDeclaration => self::declaration(null, 10, null, 1000)];
        yield 'negative maintenance' => ['maintenanceAllowance', 'maintenance_allowance.negative', static fn(): ChildminderDeclaration => self::declaration(null, 10, null, null, Amount::fromCents(-1))];
        yield 'maintenance above 9999.99' => ['maintenanceAllowance', 'maintenance_allowance.too_large', static fn(): ChildminderDeclaration => self::declaration(null, 10, null, null, $tooBig())];
        yield 'meal above 9999.99' => ['mealAllowance', 'meal_allowance.too_large', static fn(): ChildminderDeclaration => self::declaration(null, 10, null, null, null, $tooBig())];
    }

    /**
     * @param Closure(): ChildminderDeclaration $build
     */
    #[DataProvider('provideInvalidFields')]
    public function testEachRuleIsEnforced(string $path, string $code, Closure $build): void
    {
        static::assertSame([\sprintf('error %s %s', $path, $code)], DeclarationSamples::violations($build));
    }

    public function testEveryViolationIsReported(): void
    {
        $tooBig = Amount::fromCents(1_000_000);

        static::assertSame(
            [
                'error children children.empty',
                'error activityDays activity_days.out_of_range',
                'error majoredHours majored_hours.out_of_range',
                'error complementaryHours complementary_hours.out_of_range',
                'error maintenanceAllowance maintenance_allowance.too_large',
                'error mealAllowance meal_allowance.too_large',
            ],
            DeclarationSamples::violations(static fn(): ChildminderDeclaration => self::declaration([], 0, 1000, 1000, $tooBig, $tooBig)),
        );
    }

    /**
     * @param list<ChildminderChild>|null $children
     *
     * @mago-expect lint:excessive-parameter-list
     */
    private static function declaration(
        ?array $children = null,
        int $activityDays = 10,
        ?int $majoredHours = null,
        ?int $complementaryHours = null,
        ?Amount $maintenance = null,
        ?Amount $meal = null,
    ): ChildminderDeclaration {
        return ChildminderDeclaration::of(
            DeclarationSamples::common(),
            $children ?? [DeclarationSamples::childminderChild()],
            DeclarationSamples::employee(),
            null,
            $activityDays,
            $majoredHours,
            $complementaryHours,
            $maintenance,
            $meal,
        );
    }
}
