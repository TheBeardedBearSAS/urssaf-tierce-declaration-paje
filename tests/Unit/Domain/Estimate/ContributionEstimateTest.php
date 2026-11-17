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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;

final class ContributionEstimateTest extends TestCase
{
    public function testExposesEveryField(): void
    {
        $message = NonBlockingMessage::of('ER_API_041_NB_0010', 'short', 'long');

        $estimate = ContributionEstimate::of(
            DeclarationSamples::amount('120.5'),
            DeclarationSamples::amount('0'),
            DeclarationSamples::amount('-3'),
            [$message],
        );

        static::assertSame('120.50', $estimate->coveredByFamilyBenefits()?->toDecimalString());
        static::assertSame('0.00', $estimate->employerShare()?->toDecimalString());
        static::assertSame('-3.00', $estimate->employerExemption()?->toDecimalString());
        static::assertSame([$message], $estimate->messages());
    }

    public function testEveryAmountIsOptional(): void
    {
        $estimate = ContributionEstimate::of(null, null, null, []);

        static::assertNull($estimate->coveredByFamilyBenefits());
        static::assertNull($estimate->employerShare());
        static::assertNull($estimate->employerExemption());
        static::assertSame([], $estimate->messages());
    }
}
