<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

final class SyncEmployerStatusHandlerTest extends TestCase
{
    private EmployerWorld $world;

    private SyncEmployerStatusHandler $handler;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
        $this->handler = new SyncEmployerStatusHandler(
            $this->world->locator,
            $this->world->refresher,
            $this->world->accounts,
            $this->world->mandates,
        );
    }

    public function testStoresTheSnapshotAndReturnsTheAccountWithItsMandate(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);
        $this->world->gateway->queue('020', EmployerSamples::verification(false, 'Inactif'));

        $view = ($this->handler)(new SyncEmployerStatus(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertNotNull($view->lastStatus);
        static::assertFalse($view->lastStatus->authorised);
        static::assertSame('Inactif', $view->lastStatus->onlineAccountState);
        static::assertSame('2026-11-17T09:00:00Z', $view->lastStatus->checkedAt);
        static::assertSame('mandate-1', $view->mandate?->mandateId);
        static::assertSame('mandate_registered', $view->stage);
        static::assertSame('Inactif', $this->world->accounts->get(EmployerAccountId::fromString('account-1'))->lastVerification()?->state()->raw());
        static::assertSame(['EmployerStatusRefreshed'], $this->world->publishedEvents());
    }

    public function testAnUnchangedSnapshotStillMovesTheCheckInstant(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', EmployerSamples::verification(), EmployerSamples::verification());
        ($this->handler)(new SyncEmployerStatus(EmployerWorld::tenant(), EmployerWorld::selector()));
        $this->world->clock->moveTo('2026-11-18T09:00:00Z');

        $view = ($this->handler)(new SyncEmployerStatus(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame('2026-11-18T09:00:00Z', $view->lastStatus?->checkedAt);
        static::assertSame(['EmployerStatusRefreshed'], $this->world->publishedEvents());
    }

    public function testChangesNothingOnAFailure(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', UrssafUnavailable::withStatus(Operation::VerifyEmployer, 503, null, ApiErrors::none()));

        $this->expectException(UrssafUnavailable::class);

        try {
            ($this->handler)(new SyncEmployerStatus(EmployerWorld::tenant(), EmployerWorld::selector()));
        } finally {
            static::assertNull($this->world->accounts->get(EmployerAccountId::fromString('account-1'))->lastVerification());
            static::assertSame(['call:020'], $this->world->journal->entries());
        }
    }

    public function testRefusesAnUntrackedEmployer(): void
    {
        $this->expectException(RecordNotFound::class);

        ($this->handler)(new SyncEmployerStatus(EmployerWorld::tenant(), EmployerWorld::selector()));
    }
}
