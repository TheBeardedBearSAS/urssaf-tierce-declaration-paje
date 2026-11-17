<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Mandate;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event\MandateRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

final class MandateTest extends TestCase
{
    public function testRegister(): void
    {
        $mandate = Mandate::register(
            MandateSamples::id(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::accountId(),
            MandateSamples::registeredAt(),
        );

        static::assertSame('mandate-1', $mandate->id()->value());
        static::assertSame('account-1', $mandate->employerAccountId()->value());
        static::assertSame('tenant-1', $mandate->tenantId()->value());
        static::assertSame('employer-ref-1', $mandate->externalReference()->value());
        static::assertTrue($mandate->employer()->equals(EmployerSamples::employer()));
        static::assertSame('Y1869005780009', $mandate->employerNumber()->value());
        static::assertSame(MandateOrigin::Registered, $mandate->origin());
        static::assertSame(MandateState::Registered, $mandate->state());
        static::assertTrue($mandate->isActive());
        static::assertSame('2026-11-15T10:00:00Z', $mandate->registeredAt()->toIso8601());
        static::assertNull($mandate->cancelledAt());
        static::assertSame('2026-11-15T10:00:00Z', $mandate->updatedAt()->toIso8601());
        static::assertSame('2026-11-15T10:00:00Z', $mandate->timeline()->registeredAt()->toIso8601());

        $events = $mandate->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(MandateRegistered::class, $event);
        static::assertSame('mandate-1', $event->mandateId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame('account-1', $event->employerAccountId()->value());
        static::assertSame(MandateOrigin::Registered, $event->origin());
        static::assertSame('2026-11-15T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame([], $mandate->releaseEvents());
    }

    public function testImport(): void
    {
        $mandate = Mandate::import(
            MandateSamples::id(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::accountId(),
            MandateSamples::registeredAt(),
        );

        static::assertSame(MandateOrigin::Imported, $mandate->origin());
        static::assertSame(MandateState::Registered, $mandate->state());
        $events = $mandate->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(MandateRegistered::class, $events[0]);
        static::assertSame(MandateOrigin::Imported, $events[0]->origin());
    }

    public function testCancel(): void
    {
        $mandate = MandateSamples::registered();
        $now = MandateSamples::later();

        $mandate->cancel($now);

        static::assertSame(MandateState::Cancelled, $mandate->state());
        static::assertFalse($mandate->isActive());
        static::assertSame($now, $mandate->cancelledAt());
        static::assertSame($now, $mandate->updatedAt());
        static::assertSame('2026-11-15T10:00:00Z', $mandate->registeredAt()->toIso8601());
        $events = $mandate->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(MandateCancelled::class, $event);
        static::assertSame('mandate-1', $event->mandateId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame('2026-11-16T11:30:00+00:00', $event->occurredAt()->format('c'));
    }

    public function testCancelIsRefusedWhenAlreadyCancelled(): void
    {
        $mandate = MandateSamples::inState(MandateState::Cancelled);
        $cancelledAt = $mandate->cancelledAt();

        try {
            $mandate->cancel(MandateSamples::later());
            static::fail('Expected an InvalidStateTransition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('mandate.invalid_state_transition', $exception->violationCode());
            static::assertSame('cancelled', $exception->from());
            static::assertSame('cancelled', $exception->to());
        }

        static::assertSame($cancelledAt, $mandate->cancelledAt());
        static::assertSame([], $mandate->releaseEvents());
    }

    public function testEventsAreReleasedInOrder(): void
    {
        $mandate = Mandate::register(
            MandateSamples::id(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::accountId(),
            MandateSamples::registeredAt(),
        );
        $mandate->cancel(MandateSamples::later());

        static::assertSame(
            [MandateRegistered::class, MandateCancelled::class],
            array_map(static fn(DomainEvent $event): string => $event::class, $mandate->releaseEvents()),
        );
    }

    public function testNoEventCarriesAnEmployerNumberOrABirthDate(): void
    {
        $mandate = Mandate::register(
            MandateSamples::id(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::accountId(),
            MandateSamples::registeredAt(),
        );
        $mandate->cancel(MandateSamples::later());

        foreach ($mandate->releaseEvents() as $event) {
            $dump = print_r($event, true);
            static::assertStringNotContainsString('Y1869005780009', $dump, $event::class);
            static::assertStringNotContainsString('1975', $dump, $event::class);
        }
    }
}
