<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationAmountsView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationResultView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSnapshotView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationStatusView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\NonBlockingMessageView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\PreDeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\RelayReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\SubmissionErrorView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class DeclarationViewsTest extends TestCase
{
    public function testTheViewOfADeclaredRecordCarriesItsStatusAndResultWithoutPersonalData(): void
    {
        $record = RecordSamples::record(null, EmployerAccountId::fromString('account-1'));
        $record->startSubmission(RecordSamples::later(5));
        $record->markDeclared(RecordSamples::result('REF-1'), RecordSamples::later(6));

        $view = DeclarationView::fromRecord($record);

        static::assertSame('record-1', $view->recordId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame('decl-ref-1', $view->externalReference);
        static::assertSame('account-1', $view->employerAccountId);
        static::assertSame('2026-11-15T10:00:00Z', $view->createdAt);
        static::assertSame('declared', $view->status->state);
        static::assertSame(1, $view->status->attempts);
        static::assertSame('2026-11-15T10:05:00Z', $view->status->lastAttemptAt);
        static::assertSame('2026-11-15T10:06:00Z', $view->status->updatedAt);
        static::assertNull($view->status->outcomeUnknownReason);
        static::assertSame([], $view->status->errors);
        $result = $view->status->result;
        static::assertInstanceOf(DeclarationResultView::class, $result);
        static::assertSame('REF-1', $result->documentReference);
        static::assertTrue($result->pajemploiPlus);
        static::assertSame('2026-11-20', $result->transferDate);
        static::assertSame(['ER_API_DECLA_00007', 'W2'], array_map(static fn(NonBlockingMessageView $message): string => $message->code, $result->messages));
        static::assertSame('100.00', $result->amounts?->contributions);
        static::assertStringNotContainsString('Y1869005780009', serialize($view));
    }

    public function testAPendingRecordOfAnUntrackedEmployerHasNoAccountNorResultNorAttempt(): void
    {
        $view = DeclarationView::fromRecord(RecordSamples::record());

        static::assertNull($view->employerAccountId);
        static::assertSame('pending', $view->status->state);
        static::assertSame(0, $view->status->attempts);
        static::assertNull($view->status->lastAttemptAt);
        static::assertNull($view->status->result);
    }

    public function testTheStatusKeepsTheErrorsAndTheReasonOfAnUnknownOutcome(): void
    {
        $record = RecordSamples::record();
        $record->startSubmission(RecordSamples::later(1));
        $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [SubmissionError::of('E1', 'technical', 'Message', 'Description')], RecordSamples::later(2));

        $status = DeclarationStatusView::fromStatus($record->status());

        static::assertSame('outcome_unknown', $status->state);
        static::assertSame('stale_submission', $status->outcomeUnknownReason);
        static::assertCount(1, $status->errors);
        static::assertSame(['E1', 'technical', 'Message', 'Description'], [$status->errors[0]->code, $status->errors[0]->kind, $status->errors[0]->message, $status->errors[0]->description]);
        static::assertSame(
            ['code' => 'E1', 'kind' => 'technical', 'message' => '***', 'description' => '***'],
            $status->errors[0]->__debugInfo(),
        );
    }

    public function testAnErrorWithoutMessageOrDescriptionIsNotMasked(): void
    {
        $views = SubmissionErrorView::fromErrors([SubmissionError::of('E1', 'unknown', null, null)]);

        static::assertSame(['code' => 'E1', 'kind' => 'unknown', 'message' => null, 'description' => null], $views[0]->__debugInfo());
    }

    public function testTheErrorViewMasksTheMessageAndTheDescription(): void
    {
        DomainAssertions::assertSensitiveParameters(SubmissionErrorView::class, '__construct', ['message', 'description']);
    }

    public function testTheSnapshotViewHoldsTheSummaryWithoutTheEmployerNumber(): void
    {
        $snapshot = RecordSamples::snapshot();

        $view = DeclarationSnapshotView::fromSnapshot($snapshot);

        static::assertSame('childminder', $view->activity);
        static::assertSame('2026-10-01', $view->periodStart);
        static::assertSame('2026-10-31', $view->periodEnd);
        static::assertSame('2026-11-05', $view->paymentDate);
        static::assertSame('number', $view->employeeIdentificationKind);
        static::assertSame('Y2926822480004', $view->employeeNumber);
        static::assertSame($snapshot->fingerprint()->value(), $view->fingerprint);
        static::assertSame(
            [
                'activity' => 'childminder',
                'periodStart' => '2026-10-01',
                'periodEnd' => '2026-10-31',
                'paymentDate' => '2026-11-05',
                'employeeIdentificationKind' => 'number',
                'employeeNumber' => '***',
                'fingerprint' => $snapshot->fingerprint()->value(),
            ],
            $view->__debugInfo(),
        );
        static::assertStringNotContainsString('Y1869005780009', serialize($view));
    }

    public function testTheSnapshotViewOfANirEmployeeHasNoEmployeeNumber(): void
    {
        $view = DeclarationSnapshotView::fromSnapshot(DeclarationSnapshot::fromDeclaration(
            RecordSamples::employerNumber(),
            RecordSamples::homeCare(RecordSamples::nirEmployee()),
        ));

        static::assertSame('home_care', $view->activity);
        static::assertSame('nir', $view->employeeIdentificationKind);
        static::assertNull($view->employeeNumber);
        static::assertNull($view->__debugInfo()['employeeNumber']);
    }

    public function testAResultResolvedByAnOperatorHoldsTheReferenceOnly(): void
    {
        $record = RecordSamples::record();
        $record->startSubmission(RecordSamples::later(1));
        $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, [], RecordSamples::later(2));
        $record->resolveAsDeclared('REF-OP', RecordSamples::later(3));

        $result = DeclarationResultView::fromResult($this->declared($record));

        static::assertSame('REF-OP', $result->documentReference);
        static::assertNull($result->amounts);
        static::assertFalse($result->pajemploiPlus);
        static::assertNull($result->transferDate);
        static::assertSame([], $result->messages);
    }

    public function testTheAmountsViewRendersEveryFieldAsText(): void
    {
        $amounts = DeclarationAmounts::of(
            RecordSamples::amount('1'),
            RecordSamples::amount('2.5'),
            RecordSamples::amount('3'),
            WithholdingTax::of(RecordSamples::amount('4'), Rate::fromString('5.5'), RecordSamples::amount('6'), RecordSamples::amount('7')),
            RecordSamples::amount('8'),
            RecordSamples::amount('9'),
            RecordSamples::amount('10'),
            RecordSamples::amount('11'),
            RecordSamples::date('2026-12-01'),
            RecordSamples::date('2026-12-02'),
        );

        $view = DeclarationAmountsView::fromAmounts($amounts);

        static::assertSame(
            ['1.00', '2.50', '3.00', '4.00', '5.5', '6.00', '7.00', '8.00', '9.00', '10.00', '11.00', '2026-12-01', '2026-12-02'],
            [
                $view->contributions,
                $view->coveredByFamilyBenefits,
                $view->employerShare,
                $view->withholding->taxableNetSalary,
                $view->withholding->rate,
                $view->withholding->taxWithheld,
                $view->withholding->netPayableAfterTax,
                $view->cmgRemuneration,
                $view->payableToEmployee,
                $view->payableByPaje,
                $view->debitedByPaje,
                $view->plannedDebitDate,
                $view->plannedPaymentDate,
            ],
        );
    }

    public function testTheAmountsViewKeepsMissingValuesNull(): void
    {
        $view = DeclarationAmountsView::fromAmounts(DeclarationAmounts::of(
            null,
            null,
            null,
            WithholdingTax::of(null, null, null, null),
            null,
            null,
            null,
            null,
            null,
            null,
        ));

        static::assertNull($view->contributions);
        static::assertNull($view->coveredByFamilyBenefits);
        static::assertNull($view->employerShare);
        static::assertNull($view->withholding->taxableNetSalary);
        static::assertNull($view->withholding->rate);
        static::assertNull($view->withholding->taxWithheld);
        static::assertNull($view->withholding->netPayableAfterTax);
        static::assertNull($view->cmgRemuneration);
        static::assertNull($view->payableToEmployee);
        static::assertNull($view->payableByPaje);
        static::assertNull($view->debitedByPaje);
        static::assertNull($view->plannedDebitDate);
        static::assertNull($view->plannedPaymentDate);
    }

    public function testThePreDeclarationViewHoldsTheComputedAmounts(): void
    {
        $result = PreDeclarationResult::of(null, true, RecordSamples::date('2026-11-21'), [], []);

        $view = PreDeclarationView::fromResult($result);

        static::assertNull($view->amounts);
        static::assertTrue($view->pajemploiPlus);
        static::assertSame('2026-11-21', $view->transferDate);
    }

    public function testTheSelectorDesignatesARecordByIdOrReference(): void
    {
        $id = DeclarationRecordId::fromString('record-1');
        $reference = ExternalReference::fromString('ref-1');

        static::assertSame($id, DeclarationSelector::byRecordId($id)->reference);
        static::assertSame($reference, DeclarationSelector::byExternalReference($reference)->reference);
    }

    public function testTheReportsAndTheSubmissionKeepTheirValues(): void
    {
        $view = DeclarationView::fromRecord(RecordSamples::record());
        $submission = new DeclarationSubmission($view, ItemOutcome::Replayed);

        static::assertSame($view, $submission->declaration);
        static::assertSame(ItemOutcome::Replayed, $submission->outcome);
        $relay = new RelayReport(3, 2, 1);
        static::assertSame([3, 2, 1], [$relay->dispatched, $relay->failed, $relay->deferred]);
        static::assertSame(4, (new RequeueReport(4))->declarations);
    }

    private function declared(DeclarationRecord $record): DeclarationResult
    {
        $result = $record->result();
        static::assertInstanceOf(DeclarationResult::class, $result);

        return $result;
    }
}
