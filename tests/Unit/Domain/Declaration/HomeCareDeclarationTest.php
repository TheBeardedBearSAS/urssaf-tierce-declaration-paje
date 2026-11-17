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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;

final class HomeCareDeclarationTest extends TestCase
{
    public function testExposesEveryField(): void
    {
        $common = DeclarationSamples::common();
        $child = DeclarationSamples::child();
        $employee = DeclarationSamples::employee();
        $contractEnd = HomeCareContractEnd::of(null, null, null, null, null);

        $declaration = HomeCareDeclaration::of(
            CalculationMode::flatRate(),
            $common,
            [$child],
            $employee,
            $contractEnd,
            DeclarationSamples::amount('12.5'),
            3,
            4,
        );

        static::assertSame('F', $declaration->calculationMode()->value());
        static::assertSame($common, $declaration->common());
        static::assertSame([$child], $declaration->children());
        static::assertSame($employee, $declaration->employee());
        static::assertSame($contractEnd, $declaration->contractEnd());
        static::assertSame('12.50', $declaration->transportCosts()?->toDecimalString());
        static::assertSame(3, $declaration->overtimeHours25());
        static::assertSame(4, $declaration->overtimeHours50());
        static::assertSame(DeclarationActivity::HomeCare, $declaration->activityType());
    }

    public function testOptionalFieldsMayBeMissing(): void
    {
        $declaration = self::declaration();

        static::assertNotInstanceOf(HomeCareContractEnd::class, $declaration->contractEnd());
        static::assertNull($declaration->transportCosts());
        static::assertNull($declaration->overtimeHours25());
        static::assertNull($declaration->overtimeHours50());
    }

    public function testSeveralChildrenAreAccepted(): void
    {
        static::assertCount(2, self::declaration([DeclarationSamples::child(), DeclarationSamples::child()])->children());
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        $low = self::declaration(null, DeclarationSamples::amount('-1'), 0, 0);
        $high = self::declaration(null, DeclarationSamples::amount('9999.99'), 35, 40);

        static::assertSame(0, $low->overtimeHours25());
        static::assertSame(0, $low->overtimeHours50());
        static::assertSame('-1.00', $low->transportCosts()?->toDecimalString());
        static::assertSame(35, $high->overtimeHours25());
        static::assertSame(40, $high->overtimeHours50());
        static::assertSame('9999.99', $high->transportCosts()?->toDecimalString());
    }

    /**
     * @return iterable<string, array{string, string, Closure(): HomeCareDeclaration}>
     */
    public static function provideInvalidFields(): iterable
    {
        yield 'no child' => ['children', 'children.empty', static fn(): HomeCareDeclaration => self::declaration([])];
        yield 'transport above 9999.99' => ['transportCosts', 'transport_costs.too_large', static fn(): HomeCareDeclaration => self::declaration(null, Amount::fromCents(1_000_000))];
        yield 'negative overtime 25' => ['overtimeHours25', 'overtime_hours_25.out_of_range', static fn(): HomeCareDeclaration => self::declaration(null, null, -1)];
        yield 'overtime 25 above 35' => ['overtimeHours25', 'overtime_hours_25.out_of_range', static fn(): HomeCareDeclaration => self::declaration(null, null, 36)];
        yield 'negative overtime 50' => ['overtimeHours50', 'overtime_hours_50.out_of_range', static fn(): HomeCareDeclaration => self::declaration(null, null, null, -1)];
        yield 'overtime 50 above 40' => ['overtimeHours50', 'overtime_hours_50.out_of_range', static fn(): HomeCareDeclaration => self::declaration(null, null, null, 41)];
    }

    /**
     * @param Closure(): HomeCareDeclaration $build
     */
    #[DataProvider('provideInvalidFields')]
    public function testEachRuleIsEnforced(string $path, string $code, Closure $build): void
    {
        static::assertSame([\sprintf('error %s %s', $path, $code)], DeclarationSamples::violations($build));
    }

    public function testEveryViolationIsReported(): void
    {
        static::assertSame(
            [
                'error children children.empty',
                'error transportCosts transport_costs.too_large',
                'error overtimeHours25 overtime_hours_25.out_of_range',
                'error overtimeHours50 overtime_hours_50.out_of_range',
            ],
            DeclarationSamples::violations(static fn(): HomeCareDeclaration => self::declaration([], Amount::fromCents(1_000_000), 36, 41)),
        );
    }

    /**
     * @param list<Child>|null $children
     */
    private static function declaration(
        ?array $children = null,
        ?Amount $transport = null,
        ?int $overtime25 = null,
        ?int $overtime50 = null,
    ): HomeCareDeclaration {
        return HomeCareDeclaration::of(
            CalculationMode::real(),
            DeclarationSamples::common(),
            $children ?? [DeclarationSamples::child()],
            DeclarationSamples::employee(),
            null,
            $transport,
            $overtime25,
            $overtime50,
        );
    }
}
