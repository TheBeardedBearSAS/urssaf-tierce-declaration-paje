<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\UnsupportedDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;
use Throwable;

/**
 * @mago-expect lint:too-many-methods
 */
final class DeclarationSubmitterTest extends TestCase
{
    public function testADeclaredSalaryIsStoredWithItsResultAfterTheCallOutsideAnyTransaction(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Pending, 0);
        $world->gateway->queue('050', RecordSamples::result('REF-9'));

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('declared', $submission->declaration->status->state);
        static::assertSame('REF-9', $submission->declaration->status->result?->documentReference);
        static::assertSame(1, $submission->declaration->status->attempts);
        static::assertSame(
            [
                'claim:record-1',
                'call:050',
                'begin',
                'save:record-1',
                'append:urssaf.paje.declaration.declared',
                'commit',
                'publish:DeclarationDeclared',
            ],
            $world->journal->entries(),
        );
        $stored = $world->records->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::Declared, $stored->state());
        static::assertSame([], $stored->result()?->childChecks());
        static::assertSame(['050'], $world->gateway->calls());
        static::assertEquals([DeclarationWorld::tenant()], $world->gateway->tenants());
        static::assertTrue(DeclarationWorld::employer()->equals($world->gateway->employers()[0]));
    }

    public function testAHomeCareDeclarationUsesOperation051(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Pending, 0);
        $world->gateway->queue('051', RecordSamples::result());

        $submission = $this->submitDeclaration($world, RecordSamples::homeCare());

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(['051'], $world->gateway->calls());
    }

    public function testALostClaimLeavesTheRecordAloneAndSendsNothing(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Pending, 0);
        $world->records->refuseClaim(DeclarationRecordId::fromString('record-1'));

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('pending', $submission->declaration->status->state);
        static::assertSame([], $world->gateway->calls());
        static::assertSame([], $world->journal->entries());
    }

    public function testAnAlreadySubmittingRecordIsNotClaimed(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Submitting);

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('submitting', $submission->declaration->status->state);
        static::assertSame([], $world->gateway->calls());
    }

    public function testAServerErrorLeavesTheOutcomeUnknownWithItsErrors(): void
    {
        $world = $this->pendingWorld();
        $unavailable = UrssafUnavailable::withStatus(
            Operation::DeclareChildminder,
            500,
            null,
            ApiErrors::of(ApiError::of('ERR_TECHNIQUE', 'Erreur technique', 'secret detail')),
        );
        $world->gateway->queue('050', OutcomeUnknown::fromUnavailable($unavailable));

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::OutcomeUnknown, $submission->outcome);
        $record = $this->stored($world);
        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
        static::assertSame(OutcomeUnknownReason::MayHaveReachedUrssaf, $record->outcomeUnknownReason());
        static::assertSame(
            [['ERR_TECHNIQUE', 'technical', 'Erreur technique', 'secret detail']],
            self::errors($record),
        );
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'append:urssaf.paje.declaration.outcome_unknown', 'commit', 'publish:DeclarationOutcomeUnknown'],
            $world->journal->entries(),
        );
    }

    public function testATransportErrorAndAnUnreadableAnswerLeaveTheOutcomeUnknownWithoutErrors(): void
    {
        $transport = $this->pendingWorld();
        $transport->gateway->queue(
            '050',
            OutcomeUnknown::fromUnavailable(UrssafUnavailable::dueToTransportError(Operation::DeclareChildminder, new RuntimeException('x'))),
        );
        $unreadable = $this->pendingWorld();
        $unreadable->gateway->queue(
            '050',
            OutcomeUnknown::afterUnreadableResponse(Operation::DeclareChildminder, 200, UnexpectedUrssafResponse::invalidPayload('bad')),
        );

        static::assertSame(ItemOutcome::OutcomeUnknown, $this->submit($transport)->outcome);
        static::assertSame(ItemOutcome::OutcomeUnknown, $this->submit($unreadable)->outcome);
        static::assertSame([], self::errors($this->stored($transport)));
        static::assertSame([], self::errors($this->stored($unreadable)));
    }

    public function testAnUnavailabilityThatMayHaveReachedUrssafLeavesTheOutcomeUnknown(): void
    {
        $world = $this->pendingWorld();
        $world->gateway->queue(
            '050',
            UrssafUnavailable::withStatus(
                Operation::DeclareChildminder,
                503,
                null,
                ApiErrors::of(ApiError::of('ERR_TECHNIQUE', null, null)),
            ),
        );

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::OutcomeUnknown, $submission->outcome);
        static::assertSame([['ERR_TECHNIQUE', 'technical', null, null]], self::errors($this->stored($world)));
    }

    public function testADuplicateDeclarationIsRecordedAsAlreadyDeclared(): void
    {
        $world = $this->pendingWorld();
        $world->gateway->queue(
            '050',
            self::rejection(RejectionKind::DuplicateDeclaration, ApiError::of('ER_API_050_0070', 'Déjà déclarée', 'detail')),
        );

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::AlreadyDeclared, $submission->outcome);
        $record = $this->stored($world);
        static::assertSame(DeclarationState::AlreadyDeclared, $record->state());
        static::assertSame([['ER_API_050_0070', 'duplicate_declaration', 'Déjà déclarée', 'detail']], self::errors($record));
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'append:urssaf.paje.declaration.already_declared', 'commit', 'publish:DeclarationAlreadyDeclared'],
            $world->journal->entries(),
        );
    }

    public function testAnotherRejectionIsFinal(): void
    {
        $world = $this->pendingWorld();
        $world->gateway->queue(
            '050',
            self::rejection(RejectionKind::BusinessRule, ApiError::of('ER_API_050_0001', 'Refusée', null), ApiError::of('ER_API_050_0002', null, 'd')),
        );

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::Rejected, $submission->outcome);
        $record = $this->stored($world);
        static::assertSame(DeclarationState::Rejected, $record->state());
        static::assertSame(
            [['ER_API_050_0001', 'business_rule', 'Refusée', null], ['ER_API_050_0002', 'business_rule', null, 'd']],
            self::errors($record),
        );
    }

    public function testARejectionWithoutDetailsGetsAGenericError(): void
    {
        $world = $this->pendingWorld();
        $world->gateway->queue('050', UrssafRequestRejected::withErrors(Operation::DeclareChildminder, 400, ApiErrors::none(), RejectionKind::Unknown));

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::Rejected, $submission->outcome);
        static::assertSame(
            [['urssaf.rejected', 'unknown', 'URSSAF refused the declaration without error details.', null]],
            self::errors($this->stored($world)),
        );
    }

    public function testRefusedCredentialsAreRecorded(): void
    {
        $world = $this->pendingWorld();
        $world->gateway->queue('050', UrssafAuthenticationFailed::withStatus(401));

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::InvalidCredentials, $submission->outcome);
        static::assertSame(DeclarationState::InvalidCredentials, $this->stored($world)->state());
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'append:', 'commit', 'publish:DeclarationCredentialsRejected'],
            $world->journal->entries(),
        );
    }

    public function testAThrottledCallPutsTheRecordBackToPendingAndThrowsWithItsDelay(): void
    {
        $world = $this->pendingWorld();
        $world->gateway->queue('050', UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, 30, ApiErrors::none()));

        $failure = $this->failure($world);

        static::assertInstanceOf(RetryableUrssafFailure::class, $failure);
        static::assertSame(30, $failure->retryAfterSeconds());
        $record = $this->stored($world);
        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertSame(1, $record->attempts());
        static::assertSame(
            [[DeclarationSubmitter::UNAVAILABLE, 'technical', UrssafUnavailable::class, null]],
            self::errors($record),
        );
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'append:', 'commit', 'publish:DeclarationSubmissionFailed,DeclarationRequeued'],
            $this->journalWithMergedPublications($world),
        );
    }

    public function testATokenFailureAndALocalRateLimitAreRetryable(): void
    {
        $token = $this->pendingWorld();
        $token->gateway->queue('050', UrssafUnavailable::duringAuthentication(Operation::DeclareChildminder, 503, null, null));
        $limited = $this->pendingWorld();
        $limited->gateway->queue('050', RateLimitExceeded::forOperation(Operation::DeclareChildminder, 12));

        $tokenFailure = $this->failure($token);
        $limitedFailure = $this->failure($limited);

        static::assertInstanceOf(RetryableUrssafFailure::class, $tokenFailure);
        static::assertNull($tokenFailure->retryAfterSeconds());
        static::assertInstanceOf(RetryableUrssafFailure::class, $limitedFailure);
        static::assertSame(12, $limitedFailure->retryAfterSeconds());
        static::assertSame(DeclarationState::Pending, $this->stored($token)->state());
        static::assertSame(DeclarationState::Pending, $this->stored($limited)->state());
        static::assertSame(
            [[DeclarationSubmitter::UNAVAILABLE, 'technical', RateLimitExceeded::class, null]],
            self::errors($this->stored($limited)),
        );
    }

    public function testTheBodyErrorsOfAThrottledCallAreKept(): void
    {
        $world = $this->pendingWorld();
        $world->gateway->queue(
            '050',
            UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, null, ApiErrors::of(ApiError::of('ERR_QUOTA', 'Quota', 'd'))),
        );

        static::assertInstanceOf(RetryableUrssafFailure::class, $this->failure($world));

        static::assertSame([['ERR_QUOTA', 'technical', 'Quota', 'd']], self::errors($this->stored($world)));
    }

    public function testTheLastAttemptLeavesTheRecordInErrorAndReturnsExhausted(): void
    {
        $world = $this->pendingWorldAfterAttempts(4);
        $world->gateway->queue('050', UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, 5, ApiErrors::none()));

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::Exhausted, $submission->outcome);
        $record = $this->stored($world);
        static::assertSame(DeclarationState::Error, $record->state());
        static::assertSame(5, $record->attempts());
    }

    public function testTheMaximumIsComparedWithTheAttemptsMade(): void
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withMaxSubmissionAttempts(2));
        $world->record('record-1', DeclarationState::Pending, 0);
        $world->gateway->queue(
            '050',
            UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, null, ApiErrors::none()),
            UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, null, ApiErrors::none()),
        );

        $first = $this->failure($world);
        $second = $this->submit($world);

        static::assertInstanceOf(RetryableUrssafFailure::class, $first);
        static::assertSame(ItemOutcome::Exhausted, $second->outcome);
        static::assertSame(DeclarationState::Error, $this->stored($world)->state());
        static::assertSame(2, $this->stored($world)->attempts());
    }

    public function testAnotherPackageExceptionIsRethrownAfterTheRecordIsRequeued(): void
    {
        $world = $this->pendingWorld();
        $unexpected = UnexpectedUrssafResponse::invalidPayload('secret');
        $world->gateway->queue('050', $unexpected);

        $failure = $this->failure($world);

        static::assertSame($unexpected, $failure);
        $record = $this->stored($world);
        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertSame([[DeclarationSubmitter::UNEXPECTED_RESPONSE, 'unknown', UnexpectedUrssafResponse::class, null]], self::errors($record));
    }

    public function testAnotherPackageExceptionOnTheLastAttemptLeavesTheRecordInError(): void
    {
        $world = $this->pendingWorldAfterAttempts(4);
        $unexpected = UnexpectedUrssafResponse::unexpectedStatus(418);
        $world->gateway->queue('050', $unexpected);

        $failure = $this->failure($world);

        static::assertSame($unexpected, $failure);
        static::assertSame(DeclarationState::Error, $this->stored($world)->state());
    }

    public function testAnUnexpectedFailureLeavesTheOutcomeUnknownAndIsRethrownWithoutItsMessage(): void
    {
        $world = $this->pendingWorld();
        $bug = new RuntimeException('NIR 1850175123456 leaked');
        $world->gateway->queue('050', $bug);

        $failure = $this->failure($world);

        static::assertSame($bug, $failure);
        $record = $this->stored($world);
        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
        static::assertSame(OutcomeUnknownReason::MayHaveReachedUrssaf, $record->outcomeUnknownReason());
        static::assertSame([[DeclarationSubmitter::UNEXPECTED_FAILURE, 'unknown', RuntimeException::class, null]], self::errors($record));
    }

    public function testAnUnsupportedDeclarationIsRefusedBeforeAnythingIsSentAndTheRecordIsRequeued(): void
    {
        $world = $this->pendingWorld();

        $failure = $this->failureOf($world, new UnsupportedDeclaration());

        static::assertInstanceOf(InvariantViolation::class, $failure);
        static::assertSame('declaration_submission.unsupported', $failure->violationCode());
        static::assertSame([], $world->gateway->calls());
        static::assertSame(DeclarationState::Pending, $this->stored($world)->state());
    }

    public function testAnUnsupportedDeclarationIsRefusedByThePreDeclarationToo(): void
    {
        $world = $this->preDeclaringWorld();

        $failure = $this->failureOf($world, new UnsupportedDeclaration());

        static::assertInstanceOf(InvariantViolation::class, $failure);
        static::assertSame([], $world->gateway->calls());
        static::assertSame(DeclarationState::Pending, $this->stored($world)->state());
    }

    public function testThePreDeclarationIsOffByDefault(): void
    {
        $world = $this->pendingWorld();
        $world->gateway->queue('050', RecordSamples::result());

        $this->submit($world);

        static::assertSame(['050'], $world->gateway->calls());
    }

    public function testThePreDeclarationRunsFirstWhenEnabled(): void
    {
        $world = $this->preDeclaringWorld();
        $world->gateway->queue('045', PreDeclarationResult::of(null, false, null, [], []));
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(['045', '050'], $world->gateway->calls());
        static::assertSame(
            ['claim:record-1', 'call:045', 'call:050', 'begin', 'save:record-1', 'append:urssaf.paje.declaration.declared', 'commit', 'publish:DeclarationDeclared'],
            $world->journal->entries(),
        );
    }

    public function testAHomeCareDeclarationIsPreDeclaredWithOperation046(): void
    {
        $world = $this->preDeclaringWorld();
        $world->gateway->queue('046', PreDeclarationResult::of(null, false, null, [], []));
        $world->gateway->queue('051', RecordSamples::result());

        $this->submitDeclaration($world, RecordSamples::homeCare());

        static::assertSame(['046', '051'], $world->gateway->calls());
    }

    public function testADuplicateFoundByThePreDeclarationSettlesTheRecordWithoutDeclaring(): void
    {
        $world = $this->preDeclaringWorld();
        $world->gateway->queue(
            '045',
            UrssafRequestRejected::withErrors(
                Operation::PreDeclareChildminder,
                400,
                ApiErrors::of(ApiError::of('ER_API_DECLA_00020', 'Déjà', null)),
                RejectionKind::DuplicateDeclaration,
            ),
        );

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::AlreadyDeclared, $submission->outcome);
        static::assertSame(['045'], $world->gateway->calls());
        static::assertSame([['ER_API_DECLA_00020', 'duplicate_declaration', 'Déjà', null]], self::errors($this->stored($world)));
    }

    public function testARejectionOfThePreDeclarationIsFinalWithoutDeclaring(): void
    {
        $world = $this->preDeclaringWorld();
        $world->gateway->queue(
            '045',
            UrssafRequestRejected::withErrors(
                Operation::PreDeclareChildminder,
                400,
                ApiErrors::of(ApiError::of('ER_API_045_0001', null, null)),
                RejectionKind::InvalidParameter,
            ),
        );

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::Rejected, $submission->outcome);
        static::assertSame(['045'], $world->gateway->calls());
        static::assertSame(DeclarationState::Rejected, $this->stored($world)->state());
    }

    public function testRefusedCredentialsOnThePreDeclarationAreRecorded(): void
    {
        $world = $this->preDeclaringWorld();
        $world->gateway->queue('045', UrssafAuthenticationFailed::withStatus(403));

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::InvalidCredentials, $submission->outcome);
        static::assertSame(['045'], $world->gateway->calls());
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function retryablePreDeclarationFailures(): iterable
    {
        yield 'quota' => [UrssafUnavailable::withStatus(Operation::PreDeclareChildminder, 429, 4, ApiErrors::none())];
        yield 'transport error' => [UrssafUnavailable::dueToTransportError(Operation::PreDeclareChildminder, new RuntimeException('x'))];
        yield 'server error' => [UrssafUnavailable::withStatus(Operation::PreDeclareChildminder, 503, null, ApiErrors::none())];
        yield 'token' => [UrssafUnavailable::duringAuthentication(Operation::PreDeclareChildminder, null, null, null)];
        yield 'local rate limit' => [RateLimitExceeded::forOperation(Operation::PreDeclareChildminder, 1)];
    }

    #[DataProvider('retryablePreDeclarationFailures')]
    public function testAFailedPreDeclarationNeverGivesAnUnknownOutcome(Throwable $failure): void
    {
        $world = $this->preDeclaringWorld();
        $world->gateway->queue('045', $failure);

        $thrown = $this->failure($world);

        static::assertInstanceOf(RetryableUrssafFailure::class, $thrown);
        static::assertSame(['045'], $world->gateway->calls());
        $record = $this->stored($world);
        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertSame(1, $record->attempts());
    }

    public function testAFailedPreDeclarationOnTheLastAttemptIsExhausted(): void
    {
        $world = $this->preDeclaringWorldAfterAttempts(4);
        $world->gateway->queue('045', UrssafUnavailable::dueToTransportError(Operation::PreDeclareChildminder, new RuntimeException('x')));

        $submission = $this->submit($world);

        static::assertSame(ItemOutcome::Exhausted, $submission->outcome);
        static::assertSame(DeclarationState::Error, $this->stored($world)->state());
    }

    public function testAnUnexpectedFailureOfThePreDeclarationRequeuesTheRecordInsteadOfLosingTheOutcome(): void
    {
        $world = $this->preDeclaringWorld();
        $bug = new RuntimeException('boom');
        $world->gateway->queue('045', $bug);

        $failure = $this->failure($world);

        static::assertSame($bug, $failure);
        $record = $this->stored($world);
        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertSame([[DeclarationSubmitter::UNEXPECTED_FAILURE, 'unknown', RuntimeException::class, null]], self::errors($record));
    }

    public function testAnOutcomeUnknownRaisedByAPreDeclarationIsOnlyAPackageFailure(): void
    {
        $world = $this->preDeclaringWorld();
        $unknown = OutcomeUnknown::fromUnavailable(UrssafUnavailable::dueToTransportError(Operation::PreDeclareChildminder, new RuntimeException('x')));
        $world->gateway->queue('045', $unknown);

        $failure = $this->failure($world);

        static::assertSame($unknown, $failure);
        static::assertSame(DeclarationState::Pending, $this->stored($world)->state());
        static::assertSame(['045'], $world->gateway->calls());
    }

    public function testThePersonalDataIsMaskedInStackTraces(): void
    {
        DomainAssertions::assertSensitiveParameters(DeclarationSubmitter::class, 'submit', ['employer', 'declaration']);
        DomainAssertions::assertSensitiveParameters(DeclarationSubmitter::class, 'declare', ['employer', 'declaration']);
        DomainAssertions::assertSensitiveParameters(DeclarationSubmitter::class, 'preDeclare', ['employer', 'declaration']);
    }

    private function pendingWorld(): DeclarationWorld
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Pending, 0);

        return $world;
    }

    private function preDeclaringWorld(): DeclarationWorld
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withPreDeclareBeforeDeclaring(true));
        $world->record('record-1', DeclarationState::Pending, 0);

        return $world;
    }

    private function pendingWorldAfterAttempts(int $attempts): DeclarationWorld
    {
        return $this->afterAttempts(new DeclarationWorld(), $attempts);
    }

    private function preDeclaringWorldAfterAttempts(int $attempts): DeclarationWorld
    {
        return $this->afterAttempts(new DeclarationWorld((new WorkflowPolicy())->withPreDeclareBeforeDeclaring(true)), $attempts);
    }

    /**
     * Record-1 pending again after $attempts failed submissions.
     */
    private function afterAttempts(DeclarationWorld $world, int $attempts): DeclarationWorld
    {
        $record = $world->record('record-1', DeclarationState::Error, $attempts);
        $record->retry(Instant::fromIso8601(DeclarationWorld::RECORDED_AT), 10);
        $record->releaseEvents();
        $world->records->add($record);
        $world->journal->clear();

        return $world;
    }

    private function submit(DeclarationWorld $world): DeclarationSubmission
    {
        return $this->submitDeclaration($world, RecordSamples::childminder());
    }

    private function submitDeclaration(DeclarationWorld $world, Declaration $declaration): DeclarationSubmission
    {
        return $world->submitter()->submit(
            DeclarationRecordId::fromString('record-1'),
            DeclarationWorld::tenant(),
            DeclarationWorld::employer(),
            $declaration,
        );
    }

    private function failure(DeclarationWorld $world): Throwable
    {
        return $this->failureOf($world, RecordSamples::childminder());
    }

    private function failureOf(DeclarationWorld $world, Declaration $declaration): Throwable
    {
        try {
            $this->submitDeclaration($world, $declaration);
        } catch (Throwable $failure) {
            return $failure;
        }
        static::fail('Expected the submission to fail.');
    }

    private function stored(DeclarationWorld $world): DeclarationRecord
    {
        return $world->records->get(DeclarationRecordId::fromString('record-1'));
    }

    /**
     * @return list<array{string, string, ?string, ?string}>
     */
    private static function errors(DeclarationRecord $record): array
    {
        return array_map(
            static fn(SubmissionError $error): array => [$error->code(), $error->kind(), $error->message(), $error->description()],
            $record->errors(),
        );
    }

    private static function rejection(RejectionKind $kind, ApiError ...$errors): UrssafRequestRejected
    {
        return UrssafRequestRejected::withErrors(Operation::DeclareChildminder, 400, ApiErrors::of(...$errors), $kind);
    }

    /**
     * The journal with the publications (which come last, after the commit) merged into one entry "publish:A,B": the
     * publisher journals one entry per event.
     *
     * @return list<string>
     */
    private function journalWithMergedPublications(DeclarationWorld $world): array
    {
        $merged = [];
        $publications = [];
        foreach ($world->journal->entries() as $entry) {
            if (str_starts_with($entry, 'publish:')) {
                $publications[] = substr($entry, \strlen('publish:'));

                continue;
            }
            $merged[] = $entry;
        }

        return $publications === [] ? $merged : [...$merged, 'publish:' . implode(',', $publications)];
    }
}
