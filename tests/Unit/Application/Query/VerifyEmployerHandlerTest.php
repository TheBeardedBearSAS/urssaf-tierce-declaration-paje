<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

final class VerifyEmployerHandlerTest extends TestCase
{
    private EmployerWorld $world;

    private VerifyEmployerHandler $handler;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
        $this->handler = new VerifyEmployerHandler($this->world->gateway, $this->world->accounts, $this->world->refresher, $this->world->clock);
    }

    public function testReadsTheStatusOfAnUntrackedEmployerWithoutStoringAnything(): void
    {
        $this->world->gateway->queue('020', EmployerSamples::verification(true, 'Actif'));

        $view = ($this->handler)(new VerifyEmployer(EmployerWorld::tenant(), EmployerWorld::employer()));

        static::assertTrue($view->authorised);
        static::assertSame('Actif', $view->onlineAccountState);
        static::assertSame('2026-11-17T09:00:00Z', $view->checkedAt);
        static::assertSame([], $this->world->accounts->all());
        static::assertSame(['call:020'], $this->world->journal->entries());
    }

    public function testRefreshesTheSnapshotOfTheTrackedAccount(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', EmployerSamples::verification(false, 'Absent'));

        $view = ($this->handler)(new VerifyEmployer(EmployerWorld::tenant(), EmployerWorld::employer()));

        static::assertFalse($view->authorised);
        $account = $this->world->accounts->get(EmployerAccountId::fromString('account-1'));
        static::assertSame('Absent', $account->lastVerification()?->state()->raw());
        static::assertSame('2026-11-17T09:00:00Z', $account->lastCheckedAt()?->toIso8601());
        static::assertSame(['EmployerStatusRefreshed'], $this->world->publishedEvents());
    }

    public function testDoesNotRefreshAnAccountOfAnotherTenant(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', EmployerSamples::verification());

        ($this->handler)(new VerifyEmployer(EmployerWorld::tenant('tenant-2'), EmployerWorld::employer()));

        static::assertNull($this->world->accounts->get(EmployerAccountId::fromString('account-1'))->lastVerification());
    }

    public function testDoesNotRefreshAnAccountOfAnotherBirthDate(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', EmployerSamples::verification());

        ($this->handler)(new VerifyEmployer(EmployerWorld::tenant(), EmployerWorld::employer(EmployerWorld::NUMBER, '1980-01-01')));

        static::assertNull($this->world->accounts->get(EmployerAccountId::fromString('account-1'))->lastVerification());
        static::assertSame(['call:020'], $this->world->journal->entries());
    }

    public function testFailuresPropagateAndStoreNothing(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', UrssafUnavailable::withStatus(Operation::VerifyEmployer, 503, null, ApiErrors::none()));

        $this->expectException(UrssafUnavailable::class);

        try {
            ($this->handler)(new VerifyEmployer(EmployerWorld::tenant(), EmployerWorld::employer()));
        } finally {
            static::assertNull($this->world->accounts->get(EmployerAccountId::fromString('account-1'))->lastVerification());
        }
    }
}
