<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query\EstimateFixtures;

final class SimulatedPajeGatewayMandateTest extends TestCase
{
    public function testAMandateIsRegisteredAndCancelledByItsHolder(): void
    {
        $gateway = SimulatorSamples::gateway();

        $gateway->registerMandate(SimulatorSamples::tenant(), SimulatorSamples::employer());
        $registered = $gateway->isMandated(SimulatorSamples::employerNumber(), SimulatorSamples::tenant());
        $gateway->registerMandate(SimulatorSamples::tenant(), SimulatorSamples::employer());
        $gateway->cancelMandate(SimulatorSamples::tenant(), SimulatorSamples::employer());
        $cancelled = $gateway->isMandated(SimulatorSamples::employerNumber(), SimulatorSamples::tenant());
        $gateway->cancelMandate(SimulatorSamples::tenant(), SimulatorSamples::employer());

        static::assertTrue($registered);
        static::assertFalse($cancelled);
        static::assertSame(
            [Operation::RegisterMandate, Operation::RegisterMandate, Operation::CancelMandate, Operation::CancelMandate],
            $gateway->calls(),
        );
    }

    public function testAMandateHeldByAnotherTenantIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->mandate(SimulatorSamples::employerNumber(), SimulatorSamples::otherTenant());

        $register = SimulatorSamples::rejection(static fn() => $gateway->registerMandate(SimulatorSamples::tenant(), SimulatorSamples::employer()));
        $cancel = SimulatorSamples::rejection(static fn() => $gateway->cancelMandate(SimulatorSamples::tenant(), SimulatorSamples::employer()));

        static::assertSame(Operation::RegisterMandate, $register->operation());
        static::assertSame(Operation::CancelMandate, $cancel->operation());
        static::assertSame(RejectionKind::MandateHeldElsewhere, $register->kind());
        static::assertTrue($register->hasErrorCode('COMPTE_DEJA_MANDATE'));
        static::assertTrue($cancel->hasErrorCode('COMPTE_DEJA_MANDATE'));
        static::assertTrue($gateway->isMandated(SimulatorSamples::employerNumber(), SimulatorSamples::otherTenant()));
    }

    public function testAnUnknownEmployerOrABirthDateThatDiffersIsRefusedWithTheBirthDateCode(): void
    {
        $gateway = SimulatorSamples::gateway();

        $unknown = SimulatorSamples::rejection(static fn() => $gateway->registerMandate(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER)));
        $mismatch = SimulatorSamples::rejection(static fn() => $gateway->cancelMandate(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::EMPLOYER_NUMBER, '1976-02-23')));

        static::assertSame(Operation::RegisterMandate, $unknown->operation());
        static::assertSame(Operation::CancelMandate, $mismatch->operation());
        static::assertSame(RejectionKind::BirthDateMismatch, $unknown->kind());
        static::assertTrue($unknown->hasErrorCode('ER_API_020_0000'));
        static::assertTrue($mismatch->hasErrorCode('ER_API_020_0000'));
    }

    public function testEstimatesNeedNeitherAnEmployerNorAMandate(): void
    {
        $gateway = new SimulatedPajeGateway();

        $childminder = $gateway->estimateChildminder(SimulatorSamples::tenant(), EstimateFixtures::childminder());
        $homeCare = $gateway->estimateHomeCare(SimulatorSamples::tenant(), EstimateFixtures::homeCare());

        static::assertSame(SimulatedPajeGateway::DEFAULT_EMPLOYER_SHARE_CENTS, $childminder->employerShare()?->cents());
        static::assertSame(6310, $homeCare->employerShare()?->cents());
        static::assertSame([Operation::EstimateChildminder, Operation::EstimateHomeCare], $gateway->calls());
    }

    public function testTheEstimateCanBeScripted(): void
    {
        $gateway = new SimulatedPajeGateway();
        $estimate = ContributionEstimate::of(Amount::fromCents(100), Amount::fromCents(200), Amount::fromCents(300), []);
        $gateway->setEstimate($estimate);

        static::assertSame($estimate, $gateway->estimateChildminder(SimulatorSamples::tenant(), EstimateFixtures::childminder()));
        static::assertSame($estimate, $gateway->estimateHomeCare(SimulatorSamples::tenant(), EstimateFixtures::homeCare()));
    }

    public function testTheDeclarationAmountsCanBeScripted(): void
    {
        $gateway = SimulatorSamples::gateway();
        $amounts = DeclarationAmounts::of(Amount::fromCents(1), null, null, WithholdingTax::of(null, null, null, null), null, null, null, null, null, null);
        $gateway->setAmounts($amounts);

        $pre = $gateway->preDeclareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());
        $declared = $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());

        static::assertSame($amounts, $pre->amounts());
        static::assertSame($amounts, $declared->amounts());
    }
}
