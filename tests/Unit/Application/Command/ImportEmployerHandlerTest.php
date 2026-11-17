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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;

final class ImportEmployerHandlerTest extends TestCase
{
    private EmployerWorld $world;

    private ImportEmployerHandler $handler;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
        $this->handler = new ImportEmployerHandler(
            $this->world->locator,
            $this->world->accounts,
            $this->world->mandates,
            $this->world->ids,
            $this->world->clock,
            $this->world->unitOfWork,
        );
    }

    public function testImportsTheEmployerWithoutAnyCall(): void
    {
        $view = ($this->handler)(new ImportEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer()));

        static::assertSame('id-1', $view->accountId);
        static::assertSame(EmployerOrigin::Imported->value, $view->origin);
        static::assertSame(EmployerStage::Tracked->value, $view->stage);
        static::assertNull($view->lastStatus);
        static::assertNull($view->mandate);
        static::assertSame('2026-11-17T09:00:00Z', $view->createdAt);
        static::assertSame([], $this->world->gateway->calls());
        static::assertSame(['EmployerRegistered'], $this->world->publishedEvents());
        static::assertCount(1, $this->world->accounts->all());
    }

    public function testIsIdempotentForTheSameEmployer(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $view = ($this->handler)(new ImportEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer()));

        static::assertSame('account-1', $view->accountId);
        static::assertSame('mandate-1', $view->mandate?->mandateId);
        static::assertSame([], $this->world->journal->entries());
    }

    public function testRefusesTheExternalReferenceOfAnotherEmployer(): void
    {
        $this->world->trackedAccount();

        $this->expectException(EmployerAlreadyTracked::class);

        ($this->handler)(new ImportEmployer(EmployerWorld::tenant(), EmployerWorld::reference(), EmployerWorld::employer(EmployerWorld::OTHER_NUMBER)));
    }

    public function testRefusesAnEmployerTrackedUnderAnotherReference(): void
    {
        $this->world->trackedAccount();

        $this->expectException(EmployerAlreadyTracked::class);

        ($this->handler)(new ImportEmployer(EmployerWorld::tenant(), EmployerWorld::reference('employer-ref-2'), EmployerWorld::employer()));
    }
}
