<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Outbox;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\RecordingMessageBus;

final class MessengerIntegrationEventSinkTest extends TestCase
{
    public function testTheEventIsDispatchedOnTheBusAsIs(): void
    {
        $bus = new RecordingMessageBus();
        $event = self::event();

        (new MessengerIntegrationEventSink($bus))->dispatch($event);

        static::assertSame([$event], $bus->messages());
    }

    public function testAFailureOfTheBusPropagatesSoThatTheEventStaysInTheOutbox(): void
    {
        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new LogicException('transport down'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('transport down');

        (new MessengerIntegrationEventSink($bus))->dispatch(self::event());
    }

    private static function event(): DeclarationAlreadyDeclared
    {
        return new DeclarationAlreadyDeclared('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'record-1', 'declaration-1', null, 'childminder', '2026-10-01', '2026-10-31', ['ER_API_050_0070']);
    }
}
