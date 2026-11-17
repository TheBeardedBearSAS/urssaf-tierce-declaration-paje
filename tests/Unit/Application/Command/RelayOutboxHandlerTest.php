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

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IntegrationEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DomainEventSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;

final class RelayOutboxHandlerTest extends TestCase
{
    public function testThePendingEventsAreDeliveredInOrderThenMarkedAsDispatched(): void
    {
        $outbox = self::outbox();
        $sink = new RecordingIntegrationEventSink();

        $report = self::handler($outbox, $sink)(new RelayOutbox(null));

        static::assertSame([3, 0, 0], [$report->dispatched, $report->failed, $report->deferred]);
        static::assertSame([], $outbox->pending(10));
        static::assertCount(3, $sink->deliveredIds());
    }

    public function testAFailedEventStaysPendingAndHoldsBackTheNextEventsOfItsAggregateOnly(): void
    {
        $outbox = self::outbox();
        $outbox->append(...IntegrationEventTranslator::translate(DomainEventSamples::mandateCancelled()));
        $sink = new RecordingIntegrationEventSink(['record-1']);

        $report = self::handler($outbox, $sink)(new RelayOutbox(null));

        static::assertSame([2, 1, 1], [$report->dispatched, $report->failed, $report->deferred]);
        $pending = $outbox->pending(10);
        static::assertSame([1, 3], array_map(static fn(OutboxEntry $entry): int => $entry->sequence, $pending));
        static::assertSame([1, 0], array_map(static fn(OutboxEntry $entry): int => $entry->attempts, $pending));
        static::assertCount(2, $sink->deliveredIds());
    }

    public function testTheFailureIsStoredWithTheClassAndTheCodeOnly(): void
    {
        $outbox = self::outbox();
        $sink = new class implements IntegrationEventSink {
            public function dispatch(IntegrationEvent $event): void
            {
                throw new LogicException('NIR 185017512345678 for ' . $event->aggregateId, 42);
            }
        };

        self::handler($outbox, $sink)(new RelayOutbox(null));

        static::assertSame('LogicException (code 42)', $outbox->lastError(1));
    }

    public function testTheMessageOfTheSinkIsNeverStored(): void
    {
        $outbox = self::outbox();

        self::handler($outbox, new RecordingIntegrationEventSink(['record-1']))(new RelayOutbox(null));

        static::assertSame('RuntimeException (code 0)', $outbox->lastError(1));
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $outbox = self::outbox();
        $handler = new RelayOutboxHandler(
            $outbox,
            new RecordingIntegrationEventSink(),
            self::clock(),
            (new WorkflowPolicy())->withPageSizes(1, 2),
        );

        static::assertSame(1, $handler(new RelayOutbox(null))->dispatched);
        static::assertSame(2, $handler(new RelayOutbox(10))->dispatched);
        static::assertSame([], $outbox->pending(10));
    }

    public function testTheDispatchInstantIsTheCurrentInstant(): void
    {
        $outbox = self::outbox();

        self::handler($outbox, new RecordingIntegrationEventSink())(new RelayOutbox(null));

        static::assertSame(3, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-11-17T09:00:01Z')));
    }

    private static function outbox(): InMemoryOutbox
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(
            ...IntegrationEventTranslator::translate(DomainEventSamples::declarationDeclared()),
            ...IntegrationEventTranslator::translate(DomainEventSamples::mandateRegistered()),
            ...IntegrationEventTranslator::translate(DomainEventSamples::declarationAlreadyDeclared()),
        );

        return $outbox;
    }

    private static function handler(InMemoryOutbox $outbox, IntegrationEventSink $sink): RelayOutboxHandler
    {
        return new RelayOutboxHandler($outbox, $sink, self::clock(), new WorkflowPolicy());
    }

    private static function clock(): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable('2026-11-17T09:00:00Z'));
    }
}
