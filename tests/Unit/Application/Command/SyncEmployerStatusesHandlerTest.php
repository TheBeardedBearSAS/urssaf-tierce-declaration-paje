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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

final class SyncEmployerStatusesHandlerTest extends TestCase
{
    private EmployerWorld $world;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
    }

    public function testReportsEachAccountAndGoesOnAfterTemporaryFailures(): void
    {
        $this->world->trackedAccount('account-1', 'ref-1', 'Y0000000000001');
        $this->world->trackedAccount('account-2', 'ref-2', 'Y0000000000002');
        $this->world->trackedAccount('account-3', 'ref-3', 'Y0000000000003');
        $this->world->trackedAccount('account-4', 'ref-4', 'Y0000000000004');
        $this->world->trackedAccount('account-5', 'ref-5', 'Y0000000000005');
        $this->world->gateway->queue(
            '020',
            EmployerSamples::verification(),
            UrssafRequestRejected::withErrors(
                Operation::VerifyEmployer,
                400,
                ApiErrors::of(ApiError::of('EMPLOYEUR_INCONNU', null, null), ApiError::of('AUTRE', null, null)),
                RejectionKind::EmployerNotFound,
            ),
            UrssafUnavailable::withStatus(Operation::VerifyEmployer, 429, 30, ApiErrors::none()),
            RateLimitExceeded::forOperation(Operation::VerifyEmployer, 5),
            EmployerSamples::verification(),
        );

        $report = $this->handler()(new SyncEmployerStatuses(EmployerWorld::tenant(), null));

        static::assertSame(5, $report->total());
        static::assertEquals(
            [
                new BatchItem('account-1', ItemOutcome::Changed),
                new BatchItem('account-2', ItemOutcome::Failed, ['EMPLOYEUR_INCONNU', 'AUTRE']),
                new BatchItem('account-3', ItemOutcome::RetryLater),
                new BatchItem('account-4', ItemOutcome::RetryLater),
                new BatchItem('account-5', ItemOutcome::Changed),
            ],
            $report->items(),
        );
        static::assertSame(2, $report->count(ItemOutcome::Changed));
        static::assertNull($this->world->accounts->get(EmployerAccountId::fromString('account-2'))->lastVerification());
        static::assertNull($this->world->accounts->get(EmployerAccountId::fromString('account-3'))->lastVerification());
    }

    public function testReportsAnUnchangedSnapshot(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', EmployerSamples::verification(), EmployerSamples::verification());

        $first = $this->handler()(new SyncEmployerStatuses(EmployerWorld::tenant(), null));
        $second = $this->handler()(new SyncEmployerStatuses(EmployerWorld::tenant(), null));

        static::assertSame(ItemOutcome::Changed, $first->items()[0]->outcome);
        static::assertSame(ItemOutcome::Unchanged, $second->items()[0]->outcome);
    }

    public function testChecksTheLeastRecentlyCheckedFirstWithinTheLimit(): void
    {
        $this->world->trackedAccount('account-1', 'ref-1', 'Y0000000000001');
        $this->world->trackedAccount('account-2', 'ref-2', 'Y0000000000002');
        $this->world->gateway->queue('020', EmployerSamples::verification(), EmployerSamples::verification(), EmployerSamples::verification());

        $first = $this->handler()(new SyncEmployerStatuses(EmployerWorld::tenant(), 1));
        $this->world->clock->moveTo('2026-11-18T09:00:00Z');
        $second = $this->handler()(new SyncEmployerStatuses(EmployerWorld::tenant(), 1));
        $this->world->clock->moveTo('2026-11-19T09:00:00Z');
        $third = $this->handler()(new SyncEmployerStatuses(EmployerWorld::tenant(), 1));

        static::assertCount(1, $first->items());
        static::assertNotSame($first->items()[0]->subject, $second->items()[0]->subject);
        static::assertSame($first->items()[0]->subject, $third->items()[0]->subject);
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $this->world->trackedAccount('account-1', 'ref-1', 'Y0000000000001');
        $this->world->trackedAccount('account-2', 'ref-2', 'Y0000000000002');
        $this->world->gateway->queue('020', EmployerSamples::verification(), EmployerSamples::verification());

        $report = $this->handler((new WorkflowPolicy())->withPageSizes(1, 1))(new SyncEmployerStatuses(EmployerWorld::tenant(), 50));

        static::assertSame(1, $report->total());
    }

    public function testInvalidCredentialsStopTheRunAndKeepWhatWasProcessed(): void
    {
        $this->world->trackedAccount('account-1', 'ref-1', 'Y0000000000001');
        $this->world->trackedAccount('account-2', 'ref-2', 'Y0000000000002');
        $this->world->gateway->queue('020', EmployerSamples::verification(), UrssafAuthenticationFailed::withStatus(401));

        try {
            $this->handler()(new SyncEmployerStatuses(EmployerWorld::tenant(), null));
            static::fail('Expected UrssafAuthenticationFailed.');
        } catch (UrssafAuthenticationFailed) {
            static::assertNotNull($this->world->accounts->get(EmployerAccountId::fromString('account-1'))->lastVerification());
            static::assertNull($this->world->accounts->get(EmployerAccountId::fromString('account-2'))->lastVerification());
        }
    }

    public function testAnEmptyTenantGivesAnEmptyReport(): void
    {
        static::assertSame(0, $this->handler()(new SyncEmployerStatuses(EmployerWorld::tenant(), null))->total());
    }

    private function handler(WorkflowPolicy $policy = new WorkflowPolicy()): SyncEmployerStatusesHandler
    {
        return new SyncEmployerStatusesHandler($this->world->accounts, $this->world->refresher, $policy);
    }
}
