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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationCredentialsRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationRecorded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationRequeued;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationSubmissionFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * One method per command and scenario of the aggregate.
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclarationRecordTest extends TestCase
{
    private static function submitting(): DeclarationRecord
    {
        $record = RecordSamples::record();
        $record->startSubmission(RecordSamples::later(1));
        $record->releaseEvents();

        return $record;
    }

    /**
     * A record in the final state of the kind, whose last event is the only one not released.
     *
     * @throws InvalidStateTransition
     */
    private static function finalRecord(string $kind, ?EmployerAccountId $accountId): DeclarationRecord
    {
        $record = RecordSamples::record(null, $accountId);
        $record->startSubmission(RecordSamples::later(1));
        match ($kind) {
            'declared' => $record->markDeclared(RecordSamples::result(), RecordSamples::later(2)),
            'rejected' => $record->markRejected(RecordSamples::errors(), RecordSamples::later(2)),
            'already_declared' => $record->markAlreadyDeclared(RecordSamples::errors(), RecordSamples::later(2)),
            default => $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, [], RecordSamples::later(2)),
        };

        return $record;
    }

    private static function failed(int $attempts): DeclarationRecord
    {
        $record = RecordSamples::record();
        for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
            $record->startSubmission(RecordSamples::later($attempt * 10));
            $record->markFailed(RecordSamples::errors(), RecordSamples::later($attempt * 10 + 1));
            if ($attempt < $attempts) {
                $record->retry(RecordSamples::later($attempt * 10 + 2));
            }
        }
        $record->releaseEvents();

        return $record;
    }

    private static function unknown(): DeclarationRecord
    {
        $record = self::submitting();
        $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, RecordSamples::errors(), RecordSamples::later(2));
        $record->releaseEvents();

        return $record;
    }

    public function testRecordingCreatesAPendingRecordAndRecordsTheEvent(): void
    {
        $snapshot = RecordSamples::snapshot();
        $record = DeclarationRecord::record(
            RecordSamples::recordId(),
            RecordSamples::tenantId(),
            RecordSamples::externalReference(),
            $snapshot,
            RecordSamples::instant(),
            null,
        );

        static::assertTrue($record->id()->equals(RecordSamples::recordId()));
        static::assertTrue($record->tenantId()->equals(RecordSamples::tenantId()));
        static::assertTrue($record->externalReference()->equals(RecordSamples::externalReference()));
        static::assertSame($snapshot, $record->snapshot());
        static::assertEquals(RecordSamples::instant(), $record->createdAt());
        static::assertEquals(RecordSamples::instant(), $record->updatedAt());
        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertSame(DeclarationState::Pending, $record->status()->state());
        static::assertSame(0, $record->attempts());
        static::assertNull($record->lastAttemptAt());
        static::assertSame([], $record->errors());
        static::assertNull($record->result());
        static::assertNull($record->outcomeUnknownReason());

        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationRecorded::class, $event);
        static::assertTrue($event->recordId()->equals(RecordSamples::recordId()));
        static::assertTrue($event->tenantId()->equals(RecordSamples::tenantId()));
        static::assertTrue($event->externalReference()->equals(RecordSamples::externalReference()));
        static::assertSame(DeclarationActivity::Childminder, $event->activity());
        static::assertSame($snapshot->period(), $event->period());
        static::assertEquals(RecordSamples::instant()->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testTheTrackedEmployerAccountIsKeptAndCarriedByTheFinalEvents(): void
    {
        $accountId = EmployerSamples::accountId();
        $untracked = self::finalRecord('rejected', null);
        static::assertNull($untracked->employerAccountId());
        $untrackedEvent = $untracked->releaseEvents()[0];
        static::assertInstanceOf(DeclarationRejected::class, $untrackedEvent);
        static::assertNull($untrackedEvent->employerAccountId());

        foreach (['declared', 'rejected', 'already_declared', 'outcome_unknown'] as $kind) {
            $record = self::finalRecord($kind, $accountId);
            static::assertSame($accountId, $record->employerAccountId());
            $event = $record->releaseEvents()[0];
            static::assertSame($accountId, match (true) {
                $event instanceof DeclarationDeclared,
                $event instanceof DeclarationRejected,
                $event instanceof DeclarationAlreadyDeclared,
                $event instanceof DeclarationOutcomeUnknown => $event->employerAccountId(),
                default => null,
            }, $kind);
        }
    }

    public function testReleasingEventsForgetsThem(): void
    {
        $record = RecordSamples::record();
        $record->startSubmission(RecordSamples::later(1));
        $record->markRejected(RecordSamples::errors(), RecordSamples::later(2));

        static::assertCount(1, $record->releaseEvents());
        static::assertSame([], $record->releaseEvents());
    }

    public function testReleasedEventsKeepTheirOrder(): void
    {
        $record = DeclarationRecord::record(
            RecordSamples::recordId(),
            RecordSamples::tenantId(),
            RecordSamples::externalReference(),
            RecordSamples::snapshot(),
            RecordSamples::instant(),
            null,
        );
        $record->startSubmission(RecordSamples::later(1));
        $record->markRejected(RecordSamples::errors(), RecordSamples::later(2));

        $events = $record->releaseEvents();

        static::assertCount(2, $events);
        static::assertInstanceOf(DeclarationRecorded::class, $events[0]);
        static::assertInstanceOf(DeclarationRejected::class, $events[1]);
    }

    public function testStartSubmissionCountsTheAttemptWithoutEvent(): void
    {
        $record = RecordSamples::record();

        $record->startSubmission(RecordSamples::later(1));

        static::assertSame(DeclarationState::Submitting, $record->state());
        static::assertSame(1, $record->attempts());
        static::assertEquals(RecordSamples::later(1), $record->lastAttemptAt());
        static::assertEquals(RecordSamples::later(1), $record->updatedAt());
        static::assertSame([], $record->releaseEvents());
    }

    public function testMarkDeclaredKeepsTheResultWithoutChildChecks(): void
    {
        $record = self::submitting();
        $result = RecordSamples::result();
        static::assertNotSame([], $result->childChecks());

        $record->markDeclared($result, RecordSamples::later(2));

        static::assertSame(DeclarationState::Declared, $record->state());
        $kept = $record->result();
        static::assertNotNull($kept);
        static::assertSame('REF-001', $kept->documentReference());
        static::assertSame($result->amounts(), $kept->amounts());
        static::assertTrue($kept->isPajemploiPlus());
        static::assertSame($result->transferDate(), $kept->transferDate());
        static::assertSame([], $kept->childChecks());
        static::assertSame($result->messages(), $kept->messages());
        static::assertSame([], $record->errors());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationDeclared::class, $event);
        static::assertTrue($event->recordId()->equals(RecordSamples::recordId()));
        static::assertTrue($event->tenantId()->equals(RecordSamples::tenantId()));
        static::assertTrue($event->externalReference()->equals(RecordSamples::externalReference()));
        static::assertSame(DeclarationActivity::Childminder, $event->activity());
        static::assertSame($record->snapshot()->period(), $event->period());
        static::assertSame('REF-001', $event->documentReference());
        static::assertSame(['ER_API_DECLA_00007', 'W2'], $event->nonBlockingCodes());
        static::assertEquals(RecordSamples::later(2)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkDeclaredIsTheLateAcceptanceOfAnUnknownOutcomeWithTheWholeResultWithoutChildChecks(): void
    {
        $record = self::unknown();
        $result = RecordSamples::result();

        $record->markDeclared($result, RecordSamples::later(3));

        static::assertSame(DeclarationState::Declared, $record->state());
        $kept = $record->result();
        static::assertNotNull($kept);
        static::assertSame('REF-001', $kept->documentReference());
        static::assertSame($result->amounts(), $kept->amounts());
        static::assertTrue($kept->isPajemploiPlus());
        static::assertSame($result->transferDate(), $kept->transferDate());
        static::assertSame([], $kept->childChecks());
        static::assertSame($result->messages(), $kept->messages());
        static::assertSame([], $record->errors());
        static::assertNull($record->outcomeUnknownReason());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationDeclared::class, $event);
        static::assertSame('REF-001', $event->documentReference());
        static::assertSame(['ER_API_DECLA_00007', 'W2'], $event->nonBlockingCodes());
        static::assertEquals(RecordSamples::later(3)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkDeclaredKeepsTheFirstResultOfADeclaredRecord(): void
    {
        $record = self::submitting();
        $record->markDeclared(RecordSamples::result('REF-FIRST'), RecordSamples::later(2));
        $record->releaseEvents();

        RecordSamples::assertInvalidTransition(
            static fn() => $record->markDeclared(RecordSamples::result('REF-SECOND'), RecordSamples::later(3)),
            'declared',
            'declared',
        );
        static::assertSame('REF-FIRST', $record->result()?->documentReference());
        static::assertSame([], $record->releaseEvents());
    }

    public function testMarkRejectedKeepsTheErrors(): void
    {
        $record = self::submitting();

        $record->markRejected(RecordSamples::errors(), RecordSamples::later(2));

        static::assertSame(DeclarationState::Rejected, $record->state());
        static::assertEquals(RecordSamples::errors(), $record->errors());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationRejected::class, $event);
        static::assertTrue($event->recordId()->equals(RecordSamples::recordId()));
        static::assertTrue($event->tenantId()->equals(RecordSamples::tenantId()));
        static::assertTrue($event->externalReference()->equals(RecordSamples::externalReference()));
        static::assertSame(DeclarationActivity::Childminder, $event->activity());
        static::assertSame($record->snapshot()->period(), $event->period());
        static::assertSame(['ER_API_050_0070'], $event->errorCodes());
        static::assertEquals(RecordSamples::later(2)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkAlreadyDeclaredIsFinal(): void
    {
        $record = self::submitting();

        $record->markAlreadyDeclared(RecordSamples::errors(), RecordSamples::later(2));

        static::assertSame(DeclarationState::AlreadyDeclared, $record->state());
        static::assertEquals(RecordSamples::errors(), $record->errors());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationAlreadyDeclared::class, $event);
        static::assertSame(['ER_API_050_0070'], $event->errorCodes());
        static::assertTrue($event->recordId()->equals(RecordSamples::recordId()));
        static::assertTrue($event->tenantId()->equals(RecordSamples::tenantId()));
        static::assertTrue($event->externalReference()->equals(RecordSamples::externalReference()));
        static::assertSame(DeclarationActivity::Childminder, $event->activity());
        static::assertSame($record->snapshot()->period(), $event->period());
        static::assertEquals(RecordSamples::later(2)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkOutcomeUnknownKeepsTheReasonAndTheErrors(): void
    {
        $record = self::submitting();

        $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, RecordSamples::errors(), RecordSamples::later(2));

        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $record->outcomeUnknownReason());
        static::assertEquals(RecordSamples::errors(), $record->errors());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationOutcomeUnknown::class, $event);
        static::assertTrue($event->recordId()->equals(RecordSamples::recordId()));
        static::assertTrue($event->tenantId()->equals(RecordSamples::tenantId()));
        static::assertTrue($event->externalReference()->equals(RecordSamples::externalReference()));
        static::assertSame(DeclarationActivity::Childminder, $event->activity());
        static::assertSame($record->snapshot()->period(), $event->period());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $event->reason());
        static::assertEquals(RecordSamples::later(2)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkFailedKeepsTheErrors(): void
    {
        $record = self::submitting();

        $record->markFailed(RecordSamples::errors(), RecordSamples::later(2));

        static::assertSame(DeclarationState::Error, $record->state());
        static::assertEquals(RecordSamples::errors(), $record->errors());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationSubmissionFailed::class, $event);
        static::assertTrue($event->recordId()->equals(RecordSamples::recordId()));
        static::assertTrue($event->tenantId()->equals(RecordSamples::tenantId()));
        static::assertSame(['ER_API_050_0070'], $event->errorCodes());
        static::assertEquals(RecordSamples::later(2)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkInvalidCredentials(): void
    {
        $record = self::submitting();

        $record->markInvalidCredentials(RecordSamples::later(2));

        static::assertSame(DeclarationState::InvalidCredentials, $record->state());
        static::assertSame([], $record->errors());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationCredentialsRejected::class, $event);
        static::assertTrue($event->recordId()->equals(RecordSamples::recordId()));
        static::assertTrue($event->tenantId()->equals(RecordSamples::tenantId()));
        static::assertEquals(RecordSamples::later(2)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testRetryRequeuesAFailedSubmissionKeepingTheErrors(): void
    {
        $record = self::failed(1);

        $record->retry(RecordSamples::later(50));

        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertSame(1, $record->attempts());
        static::assertEquals(RecordSamples::errors(), $record->errors());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationRequeued::class, $event);
        static::assertTrue($event->recordId()->equals(RecordSamples::recordId()));
        static::assertTrue($event->tenantId()->equals(RecordSamples::tenantId()));
        static::assertSame(DeclarationState::Error, $event->from());
        static::assertEquals(RecordSamples::later(50)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testRetryRequeuesRejectedCredentialsAlways(): void
    {
        $record = self::submitting();
        $record->markInvalidCredentials(RecordSamples::later(2));
        $record->releaseEvents();

        $record->retry(RecordSamples::later(3), 1);

        static::assertSame(DeclarationState::Pending, $record->state());
        $events = $record->releaseEvents();
        static::assertInstanceOf(DeclarationRequeued::class, $events[0]);
        static::assertSame(DeclarationState::InvalidCredentials, $events[0]->from());
    }

    public function testRetryRefusesOnceTheAttemptsAreExhausted(): void
    {
        $record = self::failed(2);

        $exception = DomainAssertions::assertViolationCode(
            'declaration_record.max_attempts_reached',
            static fn() => $record->retry(RecordSamples::later(99), 2),
        );

        static::assertSame(['attempts' => 2, 'max_attempts' => 2], $exception->params());
        static::assertSame(DeclarationState::Error, $record->state());
        static::assertSame([], $record->releaseEvents());
    }

    public function testTheDefaultMaximumIsFiveAttempts(): void
    {
        static::assertTrue(self::failed(4)->canRetry());
        static::assertFalse(self::failed(5)->canRetry());
        RecordSamples::assertViolation(
            'declaration_record.max_attempts_reached',
            static fn() => self::failed(5)->retry(RecordSamples::later(99)),
        );
        self::failed(4)->retry(RecordSamples::later(99));
    }

    public function testCanRetryOnlyLimitsFailedSubmissions(): void
    {
        static::assertTrue(RecordSamples::record()->canRetry(1));
        static::assertTrue(self::unknown()->canRetry(1));
        static::assertTrue(self::failed(1)->canRetry(2));
        static::assertFalse(self::failed(1)->canRetry(1));
    }

    public function testRetryNeverRequeuesAnUnknownOutcome(): void
    {
        $record = self::unknown();

        RecordSamples::assertInvalidTransition(static fn() => $record->retry(RecordSamples::later(3)), 'outcome_unknown', 'pending');
        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
        static::assertSame([], $record->releaseEvents());
    }

    public function testResolveAsDeclaredKeepsOnlyTheReference(): void
    {
        $record = self::unknown();

        $record->resolveAsDeclared('REF-77', RecordSamples::later(3));

        static::assertSame(DeclarationState::Declared, $record->state());
        $result = $record->result();
        static::assertNotNull($result);
        static::assertSame('REF-77', $result->documentReference());
        static::assertNull($result->amounts());
        static::assertFalse($result->isPajemploiPlus());
        static::assertNull($result->transferDate());
        static::assertSame([], $result->childChecks());
        static::assertSame([], $result->messages());
        static::assertSame([], $record->errors());
        static::assertNull($record->outcomeUnknownReason());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationDeclared::class, $event);
        static::assertSame('REF-77', $event->documentReference());
        static::assertSame([], $event->nonBlockingCodes());
        static::assertEquals(RecordSamples::later(3)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testResolveAsDeclaredRequiresTheReference(): void
    {
        $record = self::unknown();

        RecordSamples::assertViolation('document_reference.empty', static fn() => $record->resolveAsDeclared(' ', RecordSamples::later(3)));

        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
    }

    public function testResolveAsDeclaredIsOnlyPossibleFromAnUnknownOutcome(): void
    {
        $record = self::submitting();

        RecordSamples::assertInvalidTransition(static fn() => $record->resolveAsDeclared('REF', RecordSamples::later(3)), 'submitting', 'declared');
        static::assertSame(DeclarationState::Submitting, $record->state());
        static::assertNull($record->result());
        static::assertSame([], $record->releaseEvents());
    }

    public function testResolveAsNotReceivedRequeuesTheRecord(): void
    {
        $record = self::unknown();

        $record->resolveAsNotReceived(RecordSamples::later(3));

        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertNull($record->outcomeUnknownReason());
        static::assertEquals(RecordSamples::errors(), $record->errors());
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DeclarationRequeued::class, $event);
        static::assertSame(DeclarationState::OutcomeUnknown, $event->from());
        static::assertEquals(RecordSamples::later(3)->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testResolveAsNotReceivedIsOnlyPossibleFromAnUnknownOutcome(): void
    {
        $record = self::submitting();

        RecordSamples::assertInvalidTransition(static fn() => $record->resolveAsNotReceived(RecordSamples::later(3)), 'submitting', 'pending');
        static::assertSame([], $record->releaseEvents());
    }

    public function testADeclaredRecordRefusesEveryCommand(): void
    {
        $record = self::submitting();
        $record->markDeclared(RecordSamples::result(), RecordSamples::later(2));
        $record->releaseEvents();
        $now = RecordSamples::later(3);

        RecordSamples::assertInvalidTransition(static fn() => $record->startSubmission($now), 'declared', 'submitting');
        RecordSamples::assertInvalidTransition(static fn() => $record->markDeclared(RecordSamples::result(), $now), 'declared', 'declared');
        RecordSamples::assertInvalidTransition(static fn() => $record->markRejected(RecordSamples::errors(), $now), 'declared', 'rejected');
        RecordSamples::assertInvalidTransition(static fn() => $record->markAlreadyDeclared(RecordSamples::errors(), $now), 'declared', 'already_declared');
        RecordSamples::assertInvalidTransition(static fn() => $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], $now), 'declared', 'outcome_unknown');
        RecordSamples::assertInvalidTransition(static fn() => $record->markFailed([], $now), 'declared', 'error');
        RecordSamples::assertInvalidTransition(static fn() => $record->markInvalidCredentials($now), 'declared', 'invalid_credentials');
        RecordSamples::assertInvalidTransition(static fn() => $record->retry($now), 'declared', 'pending');
        static::assertSame(DeclarationState::Declared, $record->state());
        static::assertSame([], $record->releaseEvents());
        static::assertEquals(RecordSamples::later(2), $record->updatedAt());
    }

    public function testIsStaleSubmission(): void
    {
        $interval = new DateInterval('PT15M');
        $record = self::submitting();

        static::assertFalse($record->isStaleSubmission(RecordSamples::later(15), $interval));
        static::assertTrue($record->isStaleSubmission(RecordSamples::later(16), $interval));
        static::assertFalse(RecordSamples::record()->isStaleSubmission(RecordSamples::later(1000), $interval));
        static::assertFalse(self::unknown()->isStaleSubmission(RecordSamples::later(1000), $interval));
    }

    public function testHasSameContentAs(): void
    {
        $record = RecordSamples::record();

        static::assertTrue($record->hasSameContentAs(RecordSamples::snapshot(RecordSamples::childminder(RecordSamples::nirEmployee()))));
        static::assertFalse($record->hasSameContentAs(RecordSamples::snapshot(RecordSamples::homeCare())));
    }
}
