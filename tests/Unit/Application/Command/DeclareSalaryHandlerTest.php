<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\DeclarationAlreadyRecorded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class DeclareSalaryHandlerTest extends TestCase
{
    public function testANewDeclarationIsRecordedThenSent(): void
    {
        $world = new DeclarationWorld();
        $world->gateway->queue('050', RecordSamples::result('REF-1'));

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('new-1', $submission->declaration->recordId);
        static::assertSame('decl-ref-1', $submission->declaration->externalReference);
        static::assertSame('declared', $submission->declaration->status->state);
        static::assertSame(
            [
                'begin',
                'save:new-1',
                'append:',
                'commit',
                'publish:DeclarationRecorded',
                'claim:new-1',
                'call:050',
                'begin',
                'save:new-1',
                'append:urssaf.paje.declaration.declared',
                'commit',
                'publish:DeclarationDeclared',
            ],
            $world->journal->entries(),
        );
        static::assertTrue(DeclarationWorld::employer()->equals($world->gateway->employers()[0]));
        static::assertEquals([DeclarationWorld::tenant()], $world->gateway->tenants());
    }

    public function testTheRecordKeepsTheSnapshotAndTheTrackedAccountOfTheEmployer(): void
    {
        $world = new DeclarationWorld();
        $world->trackedAccount();
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->declare($world);

        $stored = $world->records->get(DeclarationRecordId::fromString('new-1'));
        static::assertSame('account-1', $stored->employerAccountId()?->value());
        static::assertSame('account-1', $submission->declaration->employerAccountId);
        static::assertSame('Y1869005780009', $stored->snapshot()->employerNumber()->value());
        static::assertSame('childminder', $submission->declaration->snapshot->activity);
        static::assertTrue($stored->hasSameContentAs(RecordSamples::snapshot()));
    }

    public function testAnEmployerWithoutTrackedAccountInThisTenantLeavesTheAccountEmpty(): void
    {
        $world = new DeclarationWorld();
        $world->trackedAccount();
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->handle($world, $this->commandOf('tenant-2', RecordSamples::childminder(), false));

        static::assertNull($submission->declaration->employerAccountId);
        static::assertNull($world->records->get(DeclarationRecordId::fromString('new-1'))->employerAccountId());
    }

    public function testAHomeCareDeclarationIsSentWithOperation051(): void
    {
        $world = new DeclarationWorld();
        $world->gateway->queue('051', RecordSamples::result());

        $submission = $this->handle($world, $this->commandOf('tenant-1', RecordSamples::homeCare(), false));

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('home_care', $submission->declaration->snapshot->activity);
        static::assertSame(['051'], $world->gateway->calls());
    }

    public function testThePreDeclarationPolicyAddsTheCheckBeforeTheDeclaration(): void
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withPreDeclareBeforeDeclaring(true));
        $world->gateway->queue('045', PreDeclarationResult::of(null, false, null, [], []));
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(['045', '050'], $world->gateway->calls());
    }

    public function testWithoutMandateNothingIsRecordedNorSent(): void
    {
        $world = $this->guarded();

        $refused = $this->refusal($world, $this->command());

        static::assertSame(['operation' => '050'], $refused->params());
        $this->assertNothingHappened($world);
    }

    public function testAHomeCareDeclarationWithoutMandateIsRefusedAsMethod051(): void
    {
        $world = $this->guarded();

        $refused = $this->refusal($world, $this->commandOf('tenant-1', RecordSamples::homeCare(), false));

        static::assertSame(['operation' => Operation::DeclareHomeCare->value], $refused->params());
    }

    public function testTheMandateOfAnotherEmployerDoesNotCount(): void
    {
        $world = $this->guarded();
        $world->registeredMandate('Y1869005780010');

        static::assertSame('mandate.required', $this->refusal($world, $this->command())->violationCode());

        $this->assertNothingHappened($world);
    }

    public function testAnActiveMandateLetsTheDeclarationThrough(): void
    {
        $world = $this->guarded();
        $world->registeredMandate();
        $world->gateway->queue('050', RecordSamples::result());

        static::assertSame(ItemOutcome::Declared, $this->declare($world)->outcome);
    }

    public function testTheCommandCanSkipTheMandateCheck(): void
    {
        $world = $this->guarded();
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->handle($world, $this->commandOf('tenant-1', RecordSamples::childminder(), true));

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
    }

    public function testAThrottledCallLeavesTheRecordPendingAndThrows(): void
    {
        $world = new DeclarationWorld();
        $world->gateway->queue('050', UrssafUnavailable::withStatus(Operation::DeclareChildminder, 429, 20, ApiErrors::none()));

        try {
            $this->declare($world);
            static::fail('Expected a RetryableUrssafFailure.');
        } catch (RetryableUrssafFailure $failure) {
            static::assertSame(20, $failure->retryAfterSeconds());
        }

        static::assertSame(DeclarationState::Pending, $world->records->get(DeclarationRecordId::fromString('new-1'))->state());
    }

    public function testAPendingRecordIsSentWithoutBeingRecordedAgain(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Pending, 0);
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('record-1', $submission->declaration->recordId);
        static::assertSame('claim:record-1', $world->journal->entries()[0]);
        static::assertCount(1, $world->records->inner()->all());
    }

    public function testAPendingRecordGoesThroughTheMandateGuard(): void
    {
        $world = $this->guarded();
        $world->record('record-1', DeclarationState::Pending, 0);

        static::assertSame('mandate.required', $this->refusal($world, $this->command())->violationCode());

        static::assertSame([], $world->gateway->calls());
        static::assertSame([], $world->journal->entries());
    }

    /**
     * @return iterable<string, array{DeclarationState}>
     */
    public static function everyStateButRejected(): iterable
    {
        foreach (DeclarationState::cases() as $state) {
            if ($state === DeclarationState::Rejected) {
                continue;
            }

            yield $state->value => [$state];
        }
    }

    #[DataProvider('everyStateButRejected')]
    public function testAnotherContentIsRefusedWhateverTheStateExceptRejected(DeclarationState $state): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', $state, 1);

        try {
            $this->handle($world, $this->commandOf('tenant-1', RecordSamples::homeCare(), false));
            static::fail('Expected a DeclarationAlreadyRecorded.');
        } catch (DeclarationAlreadyRecorded $conflict) {
            static::assertSame('declaration_record.already_recorded', $conflict->violationCode());
            static::assertSame(['external_reference' => 'decl-ref-1', 'declaration_record_id' => 'record-1'], $conflict->params());
        }

        $this->assertNoCall($world);
    }

    #[DataProvider('everyStateButRejected')]
    public function testTheSameContentForAnotherEmployerIsAnotherDeclaration(DeclarationState $state): void
    {
        $world = new DeclarationWorld();
        $world->recordWith('record-1', $state, 1, RecordSamples::childminder(), 'decl-ref-1', 'tenant-1', 'Y1869005780010');

        $this->expectException(DeclarationAlreadyRecorded::class);
        $this->declare($world);
    }

    public function testASubmittingRecordIsNotClaimedAndNeedsNoMandate(): void
    {
        $world = $this->guarded();
        $world->record('record-1', DeclarationState::Submitting);

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('submitting', $submission->declaration->status->state);
        $this->assertNoCall($world);
    }

    /**
     * @return iterable<string, array{DeclarationState}>
     */
    public static function replayedStates(): iterable
    {
        yield 'declared' => [DeclarationState::Declared];
        yield 'already_declared' => [DeclarationState::AlreadyDeclared];
        yield 'outcome_unknown' => [DeclarationState::OutcomeUnknown];
        yield 'invalid_credentials' => [DeclarationState::InvalidCredentials];
    }

    #[DataProvider('replayedStates')]
    public function testAFinalOrUnknownOutcomeIsReplayedWithoutAnyCallNorMandate(DeclarationState $state): void
    {
        $world = $this->guarded();
        $world->record('record-1', $state);

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Replayed, $submission->outcome);
        static::assertSame($state->value, $submission->declaration->status->state);
        $this->assertNoCall($world);
    }

    public function testAFailedRecordWithAttemptsLeftIsRequeuedThenSent(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Error, 2);
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(3, $submission->declaration->status->attempts);
        static::assertSame(
            [
                'begin',
                'save:record-1',
                'append:',
                'commit',
                'publish:DeclarationRequeued',
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
    }

    public function testAFailedRecordWithAttemptsLeftNeedsTheMandate(): void
    {
        $world = $this->guarded();
        $world->record('record-1', DeclarationState::Error, 2);

        static::assertSame('mandate.required', $this->refusal($world, $this->command())->violationCode());

        static::assertSame(DeclarationState::Error, $world->records->get(DeclarationRecordId::fromString('record-1'))->state());
        static::assertSame([], $world->journal->entries());
    }

    public function testAnExhaustedRecordIsReturnedWithoutAnyCall(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Error, 5);

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Exhausted, $submission->outcome);
        static::assertSame('error', $submission->declaration->status->state);
        $this->assertNoCall($world);
    }

    public function testRaisingTheMaximumMakesAnExhaustedRecordRetryableAgain(): void
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withMaxSubmissionAttempts(6));
        $world->record('record-1', DeclarationState::Error, 5);
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(6, $submission->declaration->status->attempts);
    }

    /**
     * @return iterable<string, array{Declaration}>
     */
    public static function contents(): iterable
    {
        yield 'same content' => [RecordSamples::childminder()];
        yield 'another content' => [RecordSamples::homeCare()];
    }

    #[DataProvider('contents')]
    public function testARejectedRecordIsSupersededWhateverTheContent(Declaration $declaration): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Rejected);
        $world->gateway->queue($declaration->activityType()->value === 'childminder' ? '050' : '051', RecordSamples::result());

        $submission = $this->handle($world, $this->commandOf('tenant-1', $declaration, false));

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('new-1', $submission->declaration->recordId);
        static::assertSame(
            ['begin', 'delete:record-1', 'save:new-1', 'append:', 'commit', 'publish:DeclarationRecorded'],
            \array_slice($world->journal->entries(), 0, 6),
        );
        static::assertCount(1, $world->records->inner()->all());
    }

    public function testARejectedRecordIsKeptWhenTheMandateIsMissing(): void
    {
        $world = $this->guarded();
        $world->record('record-1', DeclarationState::Rejected);

        static::assertSame('mandate.required', $this->refusal($world, $this->command())->violationCode());

        static::assertSame(DeclarationState::Rejected, $world->records->get(DeclarationRecordId::fromString('record-1'))->state());
        static::assertSame([], $world->journal->entries());
    }

    public function testTheRecordOfAnotherTenantIsNotAConflict(): void
    {
        $world = new DeclarationWorld();
        $world->recordWith('record-9', DeclarationState::Declared, 1, RecordSamples::homeCare(), 'decl-ref-1', 'tenant-2', 'Y1869005780009');
        $world->gateway->queue('050', RecordSamples::result());

        $submission = $this->declare($world);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('new-1', $submission->declaration->recordId);
    }

    public function testTheCommandMasksItsPersonalData(): void
    {
        DomainAssertions::assertSensitiveParameters(DeclareSalary::class, '__construct', ['employer', 'declaration']);
        $withoutOptOut = new DeclareSalary(
            DeclarationWorld::tenant(),
            DeclarationWorld::reference(),
            DeclarationWorld::employer(),
            RecordSamples::childminder(),
        );
        static::assertFalse($withoutOptOut->skipMandateCheck);
        static::assertTrue($this->commandOf('tenant-1', RecordSamples::childminder(), true)->skipMandateCheck);
    }

    private function guarded(): DeclarationWorld
    {
        return new DeclarationWorld((new WorkflowPolicy())->withRequireLocalMandate(true));
    }

    private function command(): DeclareSalary
    {
        return $this->commandOf('tenant-1', RecordSamples::childminder(), false);
    }

    private function commandOf(string $tenant, Declaration $declaration, bool $skipMandateCheck): DeclareSalary
    {
        return new DeclareSalary(
            TenantId::fromString($tenant),
            ExternalReference::fromString('decl-ref-1'),
            DeclarationWorld::employer(),
            $declaration,
            $skipMandateCheck,
        );
    }

    private function declare(DeclarationWorld $world): DeclarationSubmission
    {
        return $this->handle($world, $this->command());
    }

    private function handle(DeclarationWorld $world, DeclareSalary $command): DeclarationSubmission
    {
        return (new DeclareSalaryHandler(
            $world->records,
            $world->accounts,
            $world->submitter(),
            $world->guard(),
            $world->ids,
            $world->clock,
            $world->unitOfWork,
            $world->policy,
        ))($command);
    }

    private function refusal(DeclarationWorld $world, DeclareSalary $command): MandateRequired
    {
        try {
            $this->handle($world, $command);
        } catch (MandateRequired $refused) {
            return $refused;
        }
        static::fail('Expected a MandateRequired.');
    }

    private function assertNothingHappened(DeclarationWorld $world): void
    {
        static::assertSame([], $world->journal->entries());
        static::assertSame([], $world->records->inner()->all());
        static::assertSame([], $world->gateway->calls());
        $this->assertNoCall($world);
    }

    private function assertNoCall(DeclarationWorld $world): void
    {
        static::assertSame([], $world->gateway->calls());
        static::assertSame([], array_filter($world->journal->entries(), static fn(string $entry): bool => str_starts_with($entry, 'call:') || str_starts_with($entry, 'claim:')));
    }
}
