<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\BufferedOutput;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\DeclarationRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandReports;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Output;
use Throwable;

/**
 * @mago-expect lint:too-many-methods
 */
final class CommandReportsTest extends TestCase
{
    public function testTheResultOfTheActionIsReturned(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::guard($output, static fn(): int => 0));
        static::assertSame(1, CommandReports::guard($output, static fn(): int => 1));
        static::assertSame('', $output->fetch());
    }

    public function testAnInvalidOptionIsAnInvalidUsage(): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw new InvalidOptionException('The --limit option must be a positive integer.'));

        static::assertSame(2, $status);
        static::assertSame("The --limit option must be a positive integer.\n", $output->fetch());
    }

    public function testAValidationFailureIsAnInvalidUsageWithOneLinePerError(): void
    {
        $output = new BufferedOutput();
        $failure = ValidationFailed::withResult(ValidationResult::of(
            Violation::error('employee.nir', 'nir.invalid_format', 'Invalid nir.'),
            Violation::warning('employee', 'employee.warning', 'A warning.'),
            Violation::error('hours', 'worked_hours.out_of_range', 'Out of range.'),
        ));

        $status = CommandReports::guard($output, static fn(): int => throw $failure);

        static::assertSame(2, $status);
        static::assertSame("employee.nir: Invalid nir. (nir.invalid_format)\nhours: Out of range. (worked_hours.out_of_range)\nInvalid input: 2 error(s).\n", $output->fetch());
    }

    public function testAnInvalidValueIsAnInvalidUsage(): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw InvalidValue::because('employer_number.invalid_format', 'Invalid employer_number.'));

        static::assertSame(2, $status);
        static::assertSame("Invalid employer_number. (employer_number.invalid_format)\n", $output->fetch());
    }

    #[DataProvider('failures')]
    public function testAnyOtherPackageFailureIsAFailureWithItsMessage(Throwable $failure, string $message): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw $failure);

        static::assertSame(1, $status);
        static::assertSame($message . "\n", $output->fetch());
    }

    /**
     * @return iterable<string, array{Throwable, string}>
     */
    public static function failures(): iterable
    {
        yield 'record not found' => [RecordNotFound::withIdentifier('declaration_record', 'x'), RecordNotFound::withIdentifier('declaration_record', 'x')->getMessage()];
        yield 'authentication failed' => [UrssafAuthenticationFailed::withStatus(401), UrssafAuthenticationFailed::withStatus(401)->getMessage()];
        yield 'invariant' => [InvariantViolation::because('workflow_policy.invalid_limit', 'A limit.'), 'A limit.'];
    }

    public function testAForeignFailureIsNotCaught(): void
    {
        $this->expectException(RuntimeException::class);

        CommandReports::guard(new BufferedOutput(), static fn(): int => throw new RuntimeException('Bug.'));
    }

    public function testAnAcceptedRegistrationThatWasNotRecordedPointsToTheImport(): void
    {
        $output = new BufferedOutput();
        $failure = MandateRecordingFailed::after(Operation::RegisterMandate, 3, 'mandate.concurrent_modification', null);

        $status = CommandReports::guard($output, static fn(): int => throw $failure);

        static::assertSame(1, $status);
        static::assertSame($failure->getMessage() . "\nURSSAF accepted the request: do not run the command again. Record the mandate without any call: urssaf:paje:mandate:import (same employer).\n", $output->fetch());
    }

    public function testAnAcceptedCancellationThatWasNotRecordedPointsToTheRecord(): void
    {
        $output = new BufferedOutput();
        $failure = MandateRecordingFailed::after(Operation::CancelMandate, 3, 'mandate.concurrent_modification', null);

        $status = CommandReports::guard($output, static fn(): int => throw $failure);

        static::assertSame(1, $status);
        static::assertSame($failure->getMessage() . "\nURSSAF accepted the request: do not run the command again. Record the cancellation without any call: urssaf:paje:mandate:record-cancellation (same employer).\n", $output->fetch());
    }

    public function testAnAcceptedDeclarationThatWasNotRecordedGivesItsDocumentReference(): void
    {
        $output = new BufferedOutput();
        $failure = DeclarationRecordingFailed::after(DeclarationRecordId::fromString('record-1'), 'DOC-1', 3, 'declaration_record.concurrent_modification', null);

        $status = CommandReports::guard($output, static fn(): int => throw $failure);

        static::assertSame(1, $status);
        static::assertSame(
            $failure->getMessage() . "\nDocument reference: DOC-1\nURSSAF accepted the declaration: never send it again. Run urssaf:paje:declarations:reconcile-stale, then urssaf:paje:declaration:resolve --declared-as=<document reference>.\n",
            $output->fetch(),
        );
    }

    public function testAnAcceptedDeclarationWithoutAReferenceStillPointsToTheResolution(): void
    {
        $output = new BufferedOutput();
        $failure = DeclarationRecordingFailed::after(DeclarationRecordId::fromString('record-1'), null, 3, 'declaration_record.concurrent_modification', null);

        static::assertSame(1, CommandReports::guard($output, static fn(): int => throw $failure));

        static::assertStringNotContainsString('Document reference', $output->fetch());
    }

    public function testARetryableFailureSaysWhenToRetry(): void
    {
        $withDelay = new BufferedOutput();
        $withoutDelay = new BufferedOutput();
        $limited = RetryableUrssafFailure::causedBy(UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, 30, ApiErrors::none()));
        $unlimited = RetryableUrssafFailure::causedBy(UrssafUnavailable::withStatus(Operation::DeclareChildminder, 503, null, ApiErrors::none()));

        static::assertSame(1, CommandReports::guard($withDelay, static fn(): int => throw $limited));
        static::assertSame(1, CommandReports::guard($withoutDelay, static fn(): int => throw $unlimited));

        static::assertSame($limited->getMessage() . "\nNothing was recorded as sent: run the command again in 30 second(s) or more.\n", $withDelay->fetch());
        static::assertSame($unlimited->getMessage() . "\nNothing was recorded as sent: run the command again later.\n", $withoutDelay->fetch());
    }

    public function testALocalRateLimitSaysWhenToRetry(): void
    {
        $output = new BufferedOutput();
        $failure = RetryableUrssafFailure::causedBy(RateLimitExceeded::forOperation(Operation::DeclareChildminder, 5));

        CommandReports::guard($output, static fn(): int => throw $failure);

        static::assertStringContainsString('in 5 second(s) or more.', $output->fetch());
    }

    public function testAReportIsATableFollowedByTheCountOfEachOutcome(): void
    {
        $output = new BufferedOutput();
        $report = BatchReport::of(
            new BatchItem('record-1', ItemOutcome::Changed),
            new BatchItem('record-2', ItemOutcome::Unchanged, ['a', 'b']),
            new BatchItem('record-3', ItemOutcome::Changed),
        );

        static::assertSame(0, CommandReports::renderReport($output, $report));

        $display = $output->fetch();
        static::assertSame([['Record', 'Outcome', 'Codes'], ['record-1', 'changed', ''], ['record-2', 'unchanged', 'a, b'], ['record-3', 'changed', '']], Output::rows($display));
        static::assertStringEndsWith("3 record(s) processed (changed: 2, unchanged: 1).\n", $display);
    }

    public function testAnEmptyReportHasNoCounts(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderReport($output, BatchReport::of()));

        static::assertStringEndsWith("0 record(s) processed.\n", $output->fetch());
    }

    #[DataProvider('failedOutcomes')]
    public function testAReportWithAFailureExitsWithOne(ItemOutcome $outcome): void
    {
        static::assertSame(1, CommandReports::renderReport(new BufferedOutput(), BatchReport::of(new BatchItem('record-1', ItemOutcome::Changed), new BatchItem('record-2', $outcome))));
    }

    /**
     * @return iterable<string, array{ItemOutcome}>
     */
    public static function failedOutcomes(): iterable
    {
        yield 'failed' => [ItemOutcome::Failed];
        yield 'invalid credentials' => [ItemOutcome::InvalidCredentials];
        yield 'manual review' => [ItemOutcome::ManualReview];
    }

    #[DataProvider('successfulOutcomes')]
    public function testAReportWithoutAFailureExitsWithZero(ItemOutcome $outcome): void
    {
        static::assertSame(0, CommandReports::renderReport(new BufferedOutput(), BatchReport::of(new BatchItem('record-1', $outcome))));
    }

    /**
     * @return iterable<string, array{ItemOutcome}>
     */
    public static function successfulOutcomes(): iterable
    {
        foreach (ItemOutcome::cases() as $outcome) {
            if (\in_array($outcome, [ItemOutcome::Failed, ItemOutcome::InvalidCredentials, ItemOutcome::ManualReview], true)) {
                continue;
            }

            yield $outcome->value => [$outcome];
        }
    }

    public function testBusyIsNotAFailure(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderBusy($output, 'urssaf_paje.outbox.relay.*'));

        static::assertSame("Already running (lock \"urssaf_paje.outbox.relay.*\" is held): skipped.\n", $output->fetch());
    }

    public function testAPlanListsTheRecordsAndSaysNothingWasCalled(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderPlan($output, [['tenant-1', 'record-1'], ['tenant-2', 'record-2']]));

        $display = $output->fetch();
        static::assertSame([['Tenant', 'Record'], ['tenant-1', 'record-1'], ['tenant-2', 'record-2']], Output::rows($display));
        static::assertStringEndsWith("Dry run: 2 record(s) would be processed, URSSAF was not called.\n", $display);
    }

    public function testAViewIsATwoColumnTable(): void
    {
        $output = new BufferedOutput();

        CommandReports::renderFields($output, [['Account id', 'a-1'], ['Stage', 'registered']]);

        static::assertSame([['Field', 'Value'], ['Account id', 'a-1'], ['Stage', 'registered']], Output::rows($output->fetch()));
    }

    public function testTheValuesAreFormattedForTheOperator(): void
    {
        static::assertSame('yes', CommandReports::yesNo(true));
        static::assertSame('no', CommandReports::yesNo(false));
        static::assertSame('-', CommandReports::yesNo(null));
        static::assertSame('value', CommandReports::orDash('value'));
        static::assertSame('', CommandReports::orDash(''));
        static::assertSame('-', CommandReports::orDash(null));
    }
}
