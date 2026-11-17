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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;

final class ContractEndTest extends TestCase
{
    public function testChildminderContractEndExposesEveryField(): void
    {
        $child = DeclarationSamples::childminderChild();
        $end = ChildminderContractEnd::of(
            DeclarationSamples::amount('1'),
            DeclarationSamples::amount('2'),
            DeclarationSamples::amount('3'),
            DeclarationSamples::amount('4'),
            DeclarationSamples::amount('5'),
            [$child],
        );

        static::assertSame('1.00', $end->retirementIndemnity()?->toDecimalString());
        static::assertSame('2.00', $end->paidLeaveCompensation()?->toDecimalString());
        static::assertSame('3.00', $end->noticeIndemnity()?->toDecimalString());
        static::assertSame('4.00', $end->precarityIndemnity()?->toDecimalString());
        static::assertSame('5.00', $end->terminationIndemnity()?->toDecimalString());
        static::assertSame([$child], $end->children());
    }

    public function testChildminderContractEndIsFullyOptional(): void
    {
        $end = ChildminderContractEnd::of(null, null, null, null, null);

        static::assertNull($end->retirementIndemnity());
        static::assertNull($end->terminationIndemnity());
        static::assertSame([], $end->children());
    }

    public function testChildminderAmountsAreCappedAndHaveNoMinimum(): void
    {
        $max = DeclarationSamples::amount('9999.99');
        $negative = DeclarationSamples::amount('-0.01');
        $end = ChildminderContractEnd::of($max, $max, $max, $max, $negative);

        static::assertSame('9999.99', $end->retirementIndemnity()?->toDecimalString());
        static::assertSame('-0.01', $end->terminationIndemnity()?->toDecimalString());
    }

    public function testChildminderEveryAmountAboveTheMaximumIsReported(): void
    {
        $tooBig = DeclarationSamples::amount('10000');

        static::assertSame(
            [
                'error retirementIndemnity retirement_indemnity.too_large',
                'error paidLeaveCompensation paid_leave_compensation.too_large',
                'error noticeIndemnity notice_indemnity.too_large',
                'error precarityIndemnity precarity_indemnity.too_large',
                'error terminationIndemnity termination_indemnity.too_large',
            ],
            DeclarationSamples::violations(static fn(): ChildminderContractEnd => ChildminderContractEnd::of(
                $tooBig,
                $tooBig,
                $tooBig,
                $tooBig,
                $tooBig,
            )),
        );
    }

    public function testHomeCareContractEndExposesEveryField(): void
    {
        $end = HomeCareContractEnd::of(
            DeclarationSamples::amount('1'),
            DeclarationSamples::amount('2'),
            DeclarationSamples::amount('3'),
            DeclarationSamples::amount('4'),
            DeclarationSamples::amount('5'),
        );

        static::assertSame('1.00', $end->retirementIndemnity()?->toDecimalString());
        static::assertSame('2.00', $end->paidLeaveCompensation()?->toDecimalString());
        static::assertSame('3.00', $end->noticeIndemnity()?->toDecimalString());
        static::assertSame('4.00', $end->precarityIndemnity()?->toDecimalString());
        static::assertSame('5.00', $end->dismissalIndemnity()?->toDecimalString());
    }

    public function testHomeCareContractEndIsFullyOptional(): void
    {
        $end = HomeCareContractEnd::of(null, null, null, null, null);

        static::assertNull($end->retirementIndemnity());
        static::assertNull($end->dismissalIndemnity());
    }

    public function testHomeCareAmountsAreCappedAndHaveNoMinimum(): void
    {
        $max = DeclarationSamples::amount('9999.99');
        $end = HomeCareContractEnd::of($max, $max, $max, $max, DeclarationSamples::amount('-0.01'));

        static::assertSame('9999.99', $end->precarityIndemnity()?->toDecimalString());
        static::assertSame('-0.01', $end->dismissalIndemnity()?->toDecimalString());
    }

    public function testHomeCareEveryAmountAboveTheMaximumIsReported(): void
    {
        $tooBig = DeclarationSamples::amount('10000.00');

        static::assertSame(
            [
                'error retirementIndemnity retirement_indemnity.too_large',
                'error paidLeaveCompensation paid_leave_compensation.too_large',
                'error noticeIndemnity notice_indemnity.too_large',
                'error precarityIndemnity precarity_indemnity.too_large',
                'error dismissalIndemnity dismissal_indemnity.too_large',
            ],
            DeclarationSamples::violations(static fn(): HomeCareContractEnd => HomeCareContractEnd::of(
                $tooBig,
                $tooBig,
                $tooBig,
                $tooBig,
                $tooBig,
            )),
        );
    }
}
