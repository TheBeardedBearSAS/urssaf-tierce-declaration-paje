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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query\EstimateFixtures;

final class SimulatedPajeGatewayDeclarationTest extends TestCase
{
    public function testADeclarationIsRecordedWithANewDocumentReference(): void
    {
        $gateway = SimulatorSamples::gateway();

        $childminder = $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());
        $homeCare = $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCare());

        static::assertSame('SIM-DECL-000001', $childminder->documentReference());
        static::assertSame('SIM-DECL-000002', $homeCare->documentReference());
        static::assertFalse($childminder->isPajemploiPlus());
        static::assertNull($childminder->transferDate());
        static::assertSame([], $childminder->childChecks());
        static::assertSame([], $childminder->messages());
        static::assertSame(SimulatedPajeGateway::DEFAULT_EMPLOYER_SHARE_CENTS, $childminder->amounts()?->employerShare()?->cents());
        static::assertSame(['SIM-DECL-000001', 'SIM-DECL-000002'], $gateway->documentReferences());
        static::assertSame([Operation::DeclareChildminder, Operation::DeclareHomeCare], $gateway->calls());
    }

    public function testAPreDeclarationRecordsNothing(): void
    {
        $gateway = SimulatorSamples::gateway();

        $first = $gateway->preDeclareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());
        $second = $gateway->preDeclareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCare());
        $again = $gateway->preDeclareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());

        foreach ([$first, $second, $again] as $result) {
            static::assertFalse($result->isPajemploiPlus());
            static::assertNull($result->transferDate());
            static::assertSame([], $result->childChecks());
            static::assertSame([], $result->messages());
            static::assertSame(6310, $result->amounts()?->employerShare()?->cents());
        }
        static::assertSame([], $gateway->documentReferences());
    }

    public function testADuplicateChildminderDeclarationIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());

        $declare = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareChildminder(SimulatorSamples::otherTenant(), SimulatorSamples::employer(), SimulatorSamples::childminder()));
        $pre = SimulatorSamples::rejection(static fn(): PreDeclarationResult => $gateway->preDeclareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder()));

        static::assertSame(Operation::DeclareChildminder, $declare->operation());
        static::assertSame(Operation::PreDeclareChildminder, $pre->operation());
        static::assertSame(RejectionKind::DuplicateDeclaration, $declare->kind());
        static::assertSame(400, $declare->httpStatus());
        static::assertTrue($declare->hasErrorCode('ER_API_050_0070'));
        static::assertTrue($pre->hasErrorCode('ER_API_050_0070'));
        static::assertSame(['SIM-DECL-000001'], $gateway->documentReferences());
    }

    public function testAChildminderDeclarationOfAnotherChildPeriodEmployeeOrEmployerIsNotADuplicate(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->knowEmployer(SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER));
        $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());
        $tenant = SimulatorSamples::tenant();

        $accepted = [
            $gateway->declareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminderFor(SimulatorSamples::byNumber(), 'LUCIE', '2026-10-01', '2026-10-31')),
            $gateway->declareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminderFor(SimulatorSamples::byNumber(), 'JEAN', '2026-11-01', '2026-11-30')),
            $gateway->declareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminderFor(SimulatorSamples::byNumber(), 'JEAN', '2026-10-01', '2026-10-30')),
            $gateway->declareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminderFor(SimulatorSamples::byNumber('Z9999999999999'), 'JEAN', '2026-10-01', '2026-10-31')),
            $gateway->declareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminderFor(SimulatorSamples::byNir(), 'JEAN', '2026-10-01', '2026-10-31')),
            $gateway->declareChildminder($tenant, SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER), SimulatorSamples::childminder()),
            $gateway->declareHomeCare($tenant, SimulatorSamples::employer(), SimulatorSamples::homeCare()),
        ];

        static::assertCount(7, $accepted);
        static::assertCount(8, $gateway->documentReferences());
    }

    public function testTheSameNirOrIdentityIsTheSameEmployee(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminderFor(SimulatorSamples::byNir(), 'JEAN', '2026-10-01', '2026-10-31'));

        $rejection = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminderFor(SimulatorSamples::byNir(), 'JEAN', '2026-10-01', '2026-10-31')));

        static::assertTrue($rejection->hasErrorCode('ER_API_050_0070'));
    }

    public function testASecondHomeCareDeclarationOfTheMonthIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCareFor(SimulatorSamples::byNumber(), '2026-10-01', '2026-10-15'));

        $declare = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCareFor(SimulatorSamples::byNumber(), '2026-10-16', '2026-10-31')));
        $pre = SimulatorSamples::rejection(static fn(): PreDeclarationResult => $gateway->preDeclareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCareFor(SimulatorSamples::byNumber(), '2026-10-16', '2026-10-31')));
        $next = $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCareFor(SimulatorSamples::byNumber(), '2026-11-01', '2026-11-30'));
        $other = $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCareFor(SimulatorSamples::byNir(), '2026-10-01', '2026-10-31'));

        static::assertSame(RejectionKind::DuplicateDeclaration, $declare->kind());
        static::assertTrue($declare->hasErrorCode('ER_API_DECLA_00020'));
        static::assertTrue($pre->hasErrorCode('ER_API_DECLA_00020'));
        static::assertSame(Operation::DeclareHomeCare, $declare->operation());
        static::assertSame(Operation::PreDeclareHomeCare, $pre->operation());
        static::assertSame('SIM-DECL-000002', $next->documentReference());
        static::assertSame('SIM-DECL-000003', $other->documentReference());
    }

    public function testADeclarationOfAnUnknownEmployerIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();
        $unknown = SimulatorSamples::employer(SimulatorSamples::OTHER_EMPLOYER_NUMBER);
        $tenant = SimulatorSamples::tenant();

        $rejections = [
            SimulatorSamples::rejection(static fn(): PreDeclarationResult => $gateway->preDeclareChildminder($tenant, $unknown, SimulatorSamples::childminder())),
            SimulatorSamples::rejection(static fn(): PreDeclarationResult => $gateway->preDeclareHomeCare($tenant, $unknown, SimulatorSamples::homeCare())),
            SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareChildminder($tenant, $unknown, SimulatorSamples::childminder())),
            SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareHomeCare($tenant, $unknown, SimulatorSamples::homeCare())),
        ];

        static::assertSame(
            [Operation::PreDeclareChildminder, Operation::PreDeclareHomeCare, Operation::DeclareChildminder, Operation::DeclareHomeCare],
            array_map(static fn(UrssafRequestRejected $rejection): Operation => $rejection->operation(), $rejections),
        );
        foreach ($rejections as $rejection) {
            static::assertSame(RejectionKind::EmployerNotFound, $rejection->kind());
            static::assertTrue($rejection->hasErrorCode('ER_API_020_0010'));
        }
        static::assertSame([], $gateway->documentReferences());
    }

    public function testADeclarationWithAnotherBirthDateIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejection = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(SimulatorSamples::EMPLOYER_NUMBER, '1976-02-23'), SimulatorSamples::childminder()));

        static::assertSame(RejectionKind::BirthDateMismatch, $rejection->kind());
    }

    public function testNoMandateIsRequiredByDefault(): void
    {
        $gateway = SimulatorSamples::gateway();

        $result = $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());

        static::assertSame('SIM-DECL-000001', $result->documentReference());
    }

    public function testTheMandateCanBeRequiredForTheDeclarationOperations(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->requireMandate();
        $tenant = SimulatorSamples::tenant();

        $rejections = [
            SimulatorSamples::rejection(static fn(): PreDeclarationResult => $gateway->preDeclareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminder())),
            SimulatorSamples::rejection(static fn(): PreDeclarationResult => $gateway->preDeclareHomeCare($tenant, SimulatorSamples::employer(), SimulatorSamples::homeCare())),
            SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminder())),
            SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareHomeCare($tenant, SimulatorSamples::employer(), SimulatorSamples::homeCare())),
        ];
        $gateway->mandate(SimulatorSamples::employerNumber(), SimulatorSamples::otherTenant());
        $elsewhere = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminder()));
        $gateway->mandate(SimulatorSamples::employerNumber(), $tenant);
        $accepted = $gateway->declareChildminder($tenant, SimulatorSamples::employer(), SimulatorSamples::childminder());
        $estimate = $gateway->estimateChildminder($tenant, EstimateFixtures::childminder());

        foreach ($rejections as $rejection) {
            static::assertSame(RejectionKind::MandateMissing, $rejection->kind());
            static::assertTrue($rejection->hasErrorCode('MANDAT_INEXISTANT'));
        }
        static::assertTrue($elsewhere->hasErrorCode('MANDAT_INEXISTANT'));
        static::assertSame('SIM-DECL-000001', $accepted->documentReference());
        static::assertSame(6310, $estimate->employerShare()?->cents());
    }
}
