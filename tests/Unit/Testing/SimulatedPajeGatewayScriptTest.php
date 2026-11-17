<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Testing;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Testing\SimulatedPajeGateway;

final class SimulatedPajeGatewayScriptTest extends TestCase
{
    public function testAScriptedFailureIsThrownBeforeAnythingIsProcessed(): void
    {
        $gateway = SimulatorSamples::gateway();
        $first = UrssafUnavailable::withStatus(Operation::DeclareChildminder, 503, 30, ApiErrors::none());
        $second = UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, 1, ApiErrors::none());
        $gateway->failNext(Operation::DeclareChildminder, $first);
        $gateway->failNext(Operation::DeclareChildminder, $second);
        $caught = [];

        foreach ([1, 2] as $_) {
            try {
                $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());
            } catch (UrssafUnavailable $failure) {
                $caught[] = $failure;
            }
        }
        $accepted = $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());

        static::assertSame([$first, $second], $caught);
        static::assertSame('SIM-DECL-000001', $accepted->documentReference());
        static::assertCount(3, $gateway->calls());
    }

    public function testAFailureOfAnotherOperationIsNotConsumed(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->failNext(Operation::DeclareHomeCare, UrssafUnavailable::withStatus(Operation::DeclareHomeCare, 503, null, ApiErrors::none()));

        $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());

        $this->expectException(UrssafUnavailable::class);
        $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCare());
    }

    public function testAScriptedRefusalReplacesTheRulesOnceAndIsClassified(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->refuseNext(Operation::DeclareHomeCare, 'ER_API_DECLA_00020', 'Déclaration existante sur la période', 'Un seul volet social par mois');
        $gateway->refuseNext(Operation::DeclareHomeCare, 'ER_API_DECLA_0001', 'La déclaration n\'est plus autorisée', null);

        $first = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCare()));
        $second = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCare()));
        $accepted = $gateway->declareHomeCare(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::homeCare());

        static::assertSame(RejectionKind::DuplicateDeclaration, $first->kind());
        static::assertSame(Operation::DeclareHomeCare, $first->operation());
        static::assertSame(400, $first->httpStatus());
        static::assertSame('Un seul volet social par mois', $first->errors()->first()?->description());
        static::assertSame(RejectionKind::DeclarationWindow, $second->kind());
        static::assertSame('SIM-DECL-000001', $accepted->documentReference());
    }

    public function testALostAnswerKeepsTheStateAndThrowsAnUnknownOutcome(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->loseNextAnswer(Operation::DeclareChildminder);

        try {
            $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());
            static::fail('Expected an OutcomeUnknown.');
        } catch (OutcomeUnknown $unknown) {
            static::assertSame(Operation::DeclareChildminder, $unknown->operation());
            static::assertSame(OutcomeUnknownReason::ServerError, $unknown->reason());
            static::assertSame(504, $unknown->httpStatus());
        }
        $duplicate = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder()));

        static::assertSame(['SIM-DECL-000001'], $gateway->documentReferences());
        static::assertTrue($duplicate->hasErrorCode('ER_API_050_0070'));
    }

    public function testALostAnswerOfARefusedCallStillThrowsAnUnknownOutcome(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());
        $gateway->loseNextAnswer(Operation::DeclareChildminder);

        try {
            $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminder());
            static::fail('Expected an OutcomeUnknown.');
        } catch (OutcomeUnknown $unknown) {
            static::assertSame(Operation::DeclareChildminder, $unknown->operation());
        }

        static::assertSame(['SIM-DECL-000001'], $gateway->documentReferences());
    }

    public function testALostAnswerWithAScriptedRefusalSwallowsTheRefusal(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->loseNextAnswer(Operation::RegisterMandate);
        $gateway->refuseNext(Operation::RegisterMandate, 'COMPTE_DEJA_MANDATE', 'Le compte est déjà mandaté', null);

        try {
            $gateway->registerMandate(SimulatorSamples::tenant(), SimulatorSamples::employer());
            static::fail('Expected an OutcomeUnknown.');
        } catch (OutcomeUnknown) {
            static::assertFalse($gateway->isMandated(SimulatorSamples::employerNumber(), SimulatorSamples::tenant()));
        }
    }

    public function testSeveralLostAnswersAreLostInOrder(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->loseNextAnswer(Operation::DeclareChildminder);
        $gateway->loseNextAnswer(Operation::DeclareChildminder);
        $lost = 0;

        foreach (['JEAN', 'LUCIE', 'PAUL'] as $firstName) {
            try {
                $gateway->declareChildminder(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::childminderFor(SimulatorSamples::byNumber(), $firstName, '2026-10-01', '2026-10-31'));
            } catch (OutcomeUnknown) {
                ++$lost;
            }
        }

        static::assertSame(2, $lost);
        static::assertCount(3, $gateway->documentReferences());
    }

    public function testALostAnswerOfAMandateKeepsTheMandate(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->loseNextAnswer(Operation::RegisterMandate);

        try {
            $gateway->registerMandate(SimulatorSamples::tenant(), SimulatorSamples::employer());
            static::fail('Expected an OutcomeUnknown.');
        } catch (OutcomeUnknown) {
            static::assertTrue($gateway->isMandated(SimulatorSamples::employerNumber(), SimulatorSamples::tenant()));
        }
    }

    public function testOnlyTheWritesCanLoseTheirAnswer(): void
    {
        $gateway = new SimulatedPajeGateway();

        foreach ([Operation::AssociateEmployee, Operation::RegisterMandate, Operation::CancelMandate, Operation::DeclareChildminder, Operation::DeclareHomeCare] as $write) {
            $gateway->loseNextAnswer($write);
        }
        foreach ([
            Operation::VerifyEmployer,
            Operation::VerifyEmployee,
            Operation::VerifyChildren,
            Operation::EstimateChildminder,
            Operation::EstimateHomeCare,
            Operation::PreDeclareChildminder,
            Operation::PreDeclareHomeCare,
        ] as $safe) {
            try {
                $gateway->loseNextAnswer($safe);
                static::fail('Expected a LogicException.');
            } catch (LogicException $logic) {
                static::assertSame(
                    'Only the writes 023, 030, 031, 050 and 051 have an unknown outcome; method ' . $safe->value . ' does not.',
                    $logic->getMessage(),
                );
            }
        }
    }

    public function testALostAssociationIsKept(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->knowEmployee(SimulatorSamples::employeeNumber(), true, false);
        $gateway->loseNextAnswer(Operation::AssociateEmployee);
        $empty = EmployeeCreation::of(null, null, null, null, null, null);

        try {
            $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNumber(), $empty);
            static::fail('Expected an OutcomeUnknown.');
        } catch (OutcomeUnknown) {
            $again = $gateway->associateEmployee(SimulatorSamples::tenant(), SimulatorSamples::employer(), SimulatorSamples::byNumber(), $empty);
            static::assertTrue($again->alreadyAssociated());
        }
    }
}
