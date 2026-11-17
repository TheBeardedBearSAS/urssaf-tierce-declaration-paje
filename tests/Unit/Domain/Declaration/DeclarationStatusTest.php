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

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class DeclarationStatusTest extends TestCase
{
    private static function pending(): DeclarationStatus
    {
        return new DeclarationStatus(DeclarationState::Pending, 0, null, [], null, null, RecordSamples::instant());
    }

    private static function submitting(): DeclarationStatus
    {
        return self::pending()->submissionStarted(RecordSamples::later(1));
    }

    private static function unknown(): DeclarationStatus
    {
        return self::submitting()->outcomeUnknown(OutcomeUnknownReason::StaleSubmission, RecordSamples::errors(), RecordSamples::later(2));
    }

    public function testRejectsANegativeAttemptCount(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'declaration_record.negative_attempts',
            static fn(): DeclarationStatus => new DeclarationStatus(DeclarationState::Pending, -1, null, [], null, null, RecordSamples::instant()),
        );
        static::assertSame(['attempts' => -1], $exception->params());
    }

    public function testZeroAttemptsIsValid(): void
    {
        static::assertSame(0, self::pending()->attempts());
    }

    public function testADeclaredStatusRequiresItsResult(): void
    {
        RecordSamples::assertViolation(
            'declaration_record.result_required',
            static fn(): DeclarationStatus => new DeclarationStatus(DeclarationState::Declared, 1, null, [], null, null, RecordSamples::instant()),
        );
        $declared = new DeclarationStatus(DeclarationState::Declared, 1, null, [], RecordSamples::result(), null, RecordSamples::instant());

        static::assertSame(DeclarationState::Declared, $declared->state());
    }

    public function testAnUnknownOutcomeRequiresItsReason(): void
    {
        RecordSamples::assertViolation(
            'declaration_record.outcome_unknown_reason_required',
            static fn(): DeclarationStatus => new DeclarationStatus(DeclarationState::OutcomeUnknown, 1, null, [], null, null, RecordSamples::instant()),
        );
    }

    public function testSubmissionStartedCountsTheAttempt(): void
    {
        $status = self::submitting();

        static::assertSame(DeclarationState::Submitting, $status->state());
        static::assertSame(1, $status->attempts());
        static::assertEquals(RecordSamples::later(1), $status->lastAttemptAt());
        static::assertEquals(RecordSamples::later(1), $status->updatedAt());
        static::assertNull($status->outcomeUnknownReason());
    }

    public function testSubmissionStartedKeepsErrorsAndResetsTheReason(): void
    {
        $requeued = self::unknown()->resolvedAsNotReceived(RecordSamples::later(3));
        $again = $requeued->submissionStarted(RecordSamples::later(4));

        static::assertSame(RecordSamples::errors()[0]->code(), $again->errors()[0]->code());
        static::assertNull($again->outcomeUnknownReason());
        static::assertSame(2, $again->attempts());
    }

    public function testDeclaredStoresTheResultAndClearsTheErrors(): void
    {
        $failed = self::submitting()->failed(RecordSamples::errors(), RecordSamples::later(2))->retried(RecordSamples::later(3));
        $declared = $failed->submissionStarted(RecordSamples::later(4))->declared(RecordSamples::result(), RecordSamples::later(5));

        static::assertSame(DeclarationState::Declared, $declared->state());
        static::assertSame([], $declared->errors());
        static::assertEquals(RecordSamples::result(), $declared->result());
        static::assertEquals(RecordSamples::later(4), $declared->lastAttemptAt());
        static::assertEquals(RecordSamples::later(5), $declared->updatedAt());
        static::assertSame(2, $declared->attempts());
    }

    public function testDeclaredIsAlsoTheLateAcceptanceOfEveryStateButDeclared(): void
    {
        $at = RecordSamples::later(9);
        $states = [
            self::pending(),
            self::unknown(),
            self::submitting()->failed(RecordSamples::errors(), RecordSamples::later(2)),
            self::submitting()->credentialsRejected(RecordSamples::later(2)),
            self::submitting()->rejected(RecordSamples::errors(), RecordSamples::later(2)),
            self::submitting()->alreadyDeclared(RecordSamples::errors(), RecordSamples::later(2)),
        ];
        foreach ($states as $status) {
            $declared = $status->declared(RecordSamples::result(), $at);

            static::assertSame(DeclarationState::Declared, $declared->state());
            static::assertSame([], $declared->errors());
            static::assertNull($declared->outcomeUnknownReason());
            static::assertEquals(RecordSamples::result(), $declared->result());
            static::assertSame($status->attempts(), $declared->attempts());
            static::assertEquals($at, $declared->updatedAt());
        }
    }

    public function testDeclaredKeepsTheFirstResult(): void
    {
        $declared = self::submitting()->declared(RecordSamples::result(), RecordSamples::later(2));

        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => $declared->declared(RecordSamples::result(), RecordSamples::later(3)), 'declared', 'declared');
    }

    public function testResolvedAsDeclaredStoresTheResultAndClearsTheErrors(): void
    {
        $resolved = self::unknown()->resolvedAsDeclared(RecordSamples::result(), RecordSamples::later(3));

        static::assertSame(DeclarationState::Declared, $resolved->state());
        static::assertSame([], $resolved->errors());
        static::assertEquals(RecordSamples::result(), $resolved->result());
        static::assertNull($resolved->outcomeUnknownReason());
        static::assertSame(1, $resolved->attempts());
        static::assertEquals(RecordSamples::later(1), $resolved->lastAttemptAt());
        static::assertEquals(RecordSamples::later(3), $resolved->updatedAt());
    }

    public function testResolvedAsDeclaredIsOnlyPossibleFromAnUnknownOutcome(): void
    {
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => self::submitting()->resolvedAsDeclared(RecordSamples::result(), RecordSamples::later(3)), 'submitting', 'declared');
    }

    public function testRejectedAlreadyDeclaredAndFailedStoreTheErrors(): void
    {
        $rejected = self::submitting()->rejected(RecordSamples::errors(), RecordSamples::later(2));
        $already = self::submitting()->alreadyDeclared(RecordSamples::errors(), RecordSamples::later(2));
        $failed = self::submitting()->failed(RecordSamples::errors(), RecordSamples::later(2));

        static::assertSame(DeclarationState::Rejected, $rejected->state());
        static::assertSame(DeclarationState::AlreadyDeclared, $already->state());
        static::assertSame(DeclarationState::Error, $failed->state());
        foreach ([$rejected, $already, $failed] as $status) {
            static::assertEquals(RecordSamples::errors(), $status->errors());
            static::assertSame(1, $status->attempts());
            static::assertEquals(RecordSamples::later(1), $status->lastAttemptAt());
            static::assertEquals(RecordSamples::later(2), $status->updatedAt());
            static::assertNull($status->result());
        }
    }

    public function testOutcomeUnknownStoresTheReasonAndTheErrors(): void
    {
        $status = self::unknown();

        static::assertSame(DeclarationState::OutcomeUnknown, $status->state());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $status->outcomeUnknownReason());
        static::assertEquals(RecordSamples::errors(), $status->errors());
        static::assertSame(1, $status->attempts());
        static::assertEquals(RecordSamples::later(1), $status->lastAttemptAt());
        static::assertEquals(RecordSamples::later(2), $status->updatedAt());
    }

    public function testCredentialsRejectedClearsTheErrors(): void
    {
        $status = self::submitting()->failed(RecordSamples::errors(), RecordSamples::later(2))->retried(RecordSamples::later(3))
            ->submissionStarted(RecordSamples::later(4))->credentialsRejected(RecordSamples::later(5));

        static::assertSame(DeclarationState::InvalidCredentials, $status->state());
        static::assertSame([], $status->errors());
    }

    public function testRetriedKeepsTheErrors(): void
    {
        $failed = self::submitting()->failed(RecordSamples::errors(), RecordSamples::later(2));
        $retried = $failed->retried(RecordSamples::later(3));

        static::assertSame(DeclarationState::Pending, $retried->state());
        static::assertEquals(RecordSamples::errors(), $retried->errors());
        static::assertSame(1, $retried->attempts());
        static::assertEquals(RecordSamples::later(3), $retried->updatedAt());
    }

    public function testRetriedNeverRequeuesAnUnknownOutcome(): void
    {
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => self::unknown()->retried(RecordSamples::later(3)), 'outcome_unknown', 'pending');
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => self::submitting()->retried(RecordSamples::later(3)), 'submitting', 'pending');
    }

    public function testResolvedAsNotReceivedKeepsTheErrorsAndClearsTheReason(): void
    {
        $status = self::unknown()->resolvedAsNotReceived(RecordSamples::later(3));

        static::assertSame(DeclarationState::Pending, $status->state());
        static::assertEquals(RecordSamples::errors(), $status->errors());
        static::assertNull($status->outcomeUnknownReason());
        static::assertSame(1, $status->attempts());
        static::assertEquals(RecordSamples::later(3), $status->updatedAt());
    }

    public function testResolvedAsNotReceivedIsOnlyPossibleFromAnUnknownOutcome(): void
    {
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => self::submitting()->failed([], RecordSamples::later(2))->resolvedAsNotReceived(RecordSamples::later(3)), 'error', 'pending');
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => self::submitting()->credentialsRejected(RecordSamples::later(2))->resolvedAsNotReceived(RecordSamples::later(3)), 'invalid_credentials', 'pending');
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => self::submitting()->resolvedAsNotReceived(RecordSamples::later(3)), 'submitting', 'pending');
    }

    public function testEveryTransitionFollowsTheStateMachine(): void
    {
        $pending = self::pending();
        $later = RecordSamples::later(1);
        $errors = RecordSamples::errors();
        $unknown = OutcomeUnknownReason::MayHaveReachedUrssaf;

        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => $pending->rejected($errors, $later), 'pending', 'rejected');
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => $pending->alreadyDeclared($errors, $later), 'pending', 'already_declared');
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => $pending->failed($errors, $later), 'pending', 'error');
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => $pending->outcomeUnknown($unknown, $errors, $later), 'pending', 'outcome_unknown');
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => $pending->credentialsRejected($later), 'pending', 'invalid_credentials');
        RecordSamples::assertInvalidTransition(static fn(): DeclarationStatus => $pending->submissionStarted($later)->submissionStarted($later), 'submitting', 'submitting');
    }

    public function testHasAttemptsLeft(): void
    {
        $status = self::submitting();

        static::assertTrue($status->hasAttemptsLeft(2));
        static::assertFalse($status->hasAttemptsLeft(1));
        static::assertFalse($status->hasAttemptsLeft(0));
    }

    public function testLastAttemptStartedBefore(): void
    {
        $interval = new DateInterval('PT15M');
        $status = self::submitting();

        static::assertFalse(self::pending()->isLastAttemptStartedBefore(RecordSamples::later(1000), $interval));
        static::assertFalse($status->isLastAttemptStartedBefore(RecordSamples::later(15), $interval));
        static::assertTrue($status->isLastAttemptStartedBefore(RecordSamples::later(16), $interval));
        static::assertTrue($status->isLastAttemptStartedBefore(RecordSamples::later(17), $interval));
    }
}
