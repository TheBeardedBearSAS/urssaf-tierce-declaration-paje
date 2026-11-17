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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellationHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;

/**
 * @mago-expect lint:too-many-methods
 */
final class MandateCommandsTest extends TestCase
{
    private EmployerWorld $world;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
    }

    public function testImportsAMandateWithoutAnyCall(): void
    {
        $this->world->trackedAccount();

        $view = $this->import()(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('id-1', $view->mandateId);
        static::assertSame(MandateOrigin::Imported->value, $view->origin);
        static::assertSame(MandateState::Registered->value, $view->state);
        static::assertSame('account-1', $view->employerAccountId);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame('id-1', $this->world->mandates->findActive(EmployerWorld::tenant(), PajeEmployerNumber::of(EmployerWorld::NUMBER))?->id()->value());
        static::assertSame(
            EmployerStage::MandateRegistered,
            $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->stage(),
        );
        static::assertSame(['MandateRegistered', 'EmployerStageChanged'], $this->world->publishedEvents());
    }

    public function testImportingAgainReturnsTheActiveMandate(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $view = $this->import()(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame([], $this->world->journal->entries());
    }

    public function testImportingCompletesTheAccountOfAnActiveMandateWithoutAnyCall(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->leftBehindMandate($account);

        $view = $this->import()(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame(EmployerStage::MandateRegistered, $this->world->accounts->get($account->id())->stage());
        static::assertCount(1, $this->world->mandates->all());
        static::assertSame(['EmployerStageChanged'], $this->world->publishedEvents());
        static::assertSame(['begin', 'append:urssaf.paje.employer.status_changed', 'commit', 'publish:EmployerStageChanged'], $this->world->journal->entries());
    }

    public function testImportingCompletesAnAccountLeftCancelledByAnActiveMandate(): void
    {
        $account = $this->world->trackedAccount();
        $account->markMandateCancelled(Instant::fromIso8601('2026-11-02T00:00:00Z'));
        $account->releaseEvents();
        $this->world->accounts->save($account);
        $this->world->leftBehindMandate($this->world->accounts->get($account->id()), 'mandate-2', MandateOrigin::Imported);

        $view = $this->import()(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-2', $view->mandateId);
        static::assertSame(MandateOrigin::Imported->value, $view->origin);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame(EmployerStage::MandateRegistered, $this->world->accounts->get($account->id())->stage());
        static::assertSame(['EmployerStageChanged'], $this->world->publishedEvents());
    }

    public function testImportingTwiceAfterTheCompletionChangesNothing(): void
    {
        $this->world->leftBehindMandate($this->world->trackedAccount());
        $this->import()(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
        $this->world->journal->clear();

        $view = $this->import()(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame([], $this->world->journal->entries());
    }

    public function testCompletesTheAccountOfAnActiveMandateWithoutAnyCall(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->leftBehindMandate($account);

        $view = $this->register()(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame(EmployerStage::MandateRegistered, $this->world->accounts->get($account->id())->stage());
        static::assertCount(1, $this->world->mandates->all());
        static::assertSame(['EmployerStageChanged'], $this->world->publishedEvents());
    }

    public function testImportRefusesAnUntrackedEmployer(): void
    {
        $this->expectException(RecordNotFound::class);

        $this->import()(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
    }

    public function testCancelsTheMandateAfterTheCall(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);
        $this->world->gateway->queue('031', null);

        $view = $this->cancel()(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame(MandateState::Cancelled->value, $view->state);
        static::assertSame('2026-11-17T09:00:00Z', $view->cancelledAt);
        static::assertSame(['031'], $this->world->gateway->calls());
        static::assertNull($this->world->mandates->findActive(EmployerWorld::tenant(), PajeEmployerNumber::of(EmployerWorld::NUMBER)));
        static::assertSame(
            EmployerStage::MandateCancelled,
            $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->stage(),
        );
        static::assertSame(
            [
                'call:031',
                'begin',
                'append:urssaf.paje.mandate.cancelled,urssaf.paje.employer.status_changed',
                'commit',
                'publish:MandateCancelled',
                'publish:EmployerStageChanged',
            ],
            $this->world->journal->entries(),
        );
    }

    public function testCancelIsRefusedWithoutAnActiveMandateAndWithoutAnyCall(): void
    {
        $this->world->trackedAccount();

        try {
            $this->cancel()(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('mandate.not_found', $notFound->violationCode());
            static::assertSame('account-1', $notFound->identifier());
            static::assertSame([], $this->world->gateway->calls());
        }
    }

    public function testCancelKeepsTheMandateOnAnUnknownOutcome(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);
        $unknown = OutcomeUnknown::fromUnavailable(UrssafUnavailable::withStatus(Operation::CancelMandate, 500, null, ApiErrors::none()));
        $this->world->gateway->queue('031', $unknown);

        try {
            $this->cancel()(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
            static::fail('Expected OutcomeUnknown.');
        } catch (OutcomeUnknown $caught) {
            static::assertSame($unknown, $caught);
        }

        $this->assertMandateStillActive();
    }

    public function testCancelKeepsTheMandateWhenUrssafRejectsIt(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);
        $this->world->gateway->queue(
            '031',
            UrssafRequestRejected::withErrors(Operation::CancelMandate, 400, ApiErrors::none(), RejectionKind::MandateMissing),
        );

        $this->expectException(UrssafRequestRejected::class);

        try {
            $this->cancel()(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
        } finally {
            $this->assertMandateStillActive();
        }
    }

    public function testRecordsACancellationWithoutAnyCall(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $view = $this->recordCancellation()(new RecordMandateCancellation(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame(MandateState::Cancelled->value, $view->state);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame(EmployerStage::MandateCancelled, $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        static::assertSame(['MandateCancelled', 'EmployerStageChanged'], $this->world->publishedEvents());
    }

    public function testRecordingACancellationAgainChangesNothing(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);
        $this->recordCancellation()(new RecordMandateCancellation(EmployerWorld::tenant(), EmployerWorld::selector()));
        $this->world->journal->clear();

        $view = $this->recordCancellation()(new RecordMandateCancellation(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame(MandateState::Cancelled->value, $view->state);
        static::assertSame(['begin', 'append:', 'commit'], $this->world->journal->entries());
    }

    public function testRecordingACancellationReturnsTheLastCancelledMandateAndCompletesTheAccount(): void
    {
        $account = $this->world->trackedAccount();
        $first = $this->world->registeredMandate($account, 'mandate-1');
        $first->cancel(Instant::fromIso8601('2026-11-03T00:00:00Z'));
        $this->world->mandates->save($first);
        $second = $this->world->registeredMandate($account, 'mandate-2');
        $second->cancel(Instant::fromIso8601('2026-11-04T00:00:00Z'));
        $this->world->mandates->save($second);

        $view = $this->recordCancellation()(new RecordMandateCancellation(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('mandate-2', $view->mandateId);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame(EmployerStage::MandateCancelled, $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        static::assertSame(['EmployerStageChanged'], $this->world->publishedEvents());
    }

    public function testACancellationOfAnOldMandateLeavesTheAccountOfANewMandateAlone(): void
    {
        $account = $this->world->trackedAccount();
        $old = $this->world->registeredMandate($account, 'mandate-1');
        $old->cancel(Instant::fromIso8601('2026-11-03T00:00:00Z'));
        $this->world->mandates->save($old);
        $this->world->registeredMandate($this->world->accounts->get($account->id()), 'mandate-2');

        $view = $this->world->recorder->recordCancellation($account->id(), $old->id());

        static::assertSame('mandate-1', $view->id()->value());
        static::assertSame(EmployerStage::MandateRegistered, $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        static::assertSame([], $this->world->publishedEvents());
    }

    public function testRecordingACancellationWithoutAnyMandateIsRefused(): void
    {
        $this->world->trackedAccount();

        try {
            $this->recordCancellation()(new RecordMandateCancellation(EmployerWorld::tenant(), EmployerWorld::selector()));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('mandate.not_found', $notFound->violationCode());
            static::assertSame([], $this->world->journal->entries());
        }
    }

    public function testAnActiveMandateAlreadyRecordedIsNotRegisteredAgainButTheAccountIsCompleted(): void
    {
        $account = $this->world->trackedAccount();
        $mandate = Mandate::register(
            MandateId::fromString('mandate-1'),
            $account->tenantId(),
            $account->externalReference(),
            $account->employer(),
            $account->id(),
            Instant::fromIso8601('2026-11-02T08:00:00Z'),
        );
        $this->world->mandates->save($mandate);

        $recorded = $this->world->recorder->recordRegistration($account->id());

        static::assertSame('mandate-1', $recorded->id()->value());
        static::assertSame(EmployerStage::MandateRegistered, $this->world->accounts->get($account->id())->stage());
        static::assertSame(['EmployerStageChanged'], $this->world->publishedEvents());
    }

    private function recordCancellation(): RecordMandateCancellationHandler
    {
        return new RecordMandateCancellationHandler($this->world->locator, $this->world->mandates, $this->world->recorder);
    }

    private function assertMandateStillActive(): void
    {
        static::assertNotNull($this->world->mandates->findActive(EmployerWorld::tenant(), PajeEmployerNumber::of(EmployerWorld::NUMBER)));
        static::assertSame(
            EmployerStage::MandateRegistered,
            $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->stage(),
        );
        static::assertSame(['call:031'], $this->world->journal->entries());
    }

    private function import(): ImportMandateHandler
    {
        return new ImportMandateHandler($this->world->locator, $this->world->mandates, $this->world->recorder);
    }

    private function register(): RegisterMandateHandler
    {
        return new RegisterMandateHandler($this->world->locator, $this->world->mandates, $this->world->gateway, $this->world->recorder);
    }

    private function cancel(): CancelMandateHandler
    {
        return new CancelMandateHandler(
            $this->world->locator,
            $this->world->mandates,
            $this->world->gateway,
            $this->world->recorder,
        );
    }
}
