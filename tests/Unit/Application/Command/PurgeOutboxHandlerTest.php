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

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IntegrationEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DomainEventSamples;

final class PurgeOutboxHandlerTest extends TestCase
{
    public function testTheEntriesDispatchedBeforeTheRetentionAreDeleted(): void
    {
        $outbox = self::dispatchedOutbox();

        $deleted = self::handler($outbox, new WorkflowPolicy())(new PurgeOutbox(new DateInterval('P2D')));

        static::assertSame(2, $deleted);
        static::assertSame(1, self::handler($outbox, new WorkflowPolicy())(new PurgeOutbox(new DateInterval('PT1H'))));
    }

    public function testThePolicyRetentionIsTheDefault(): void
    {
        $outbox = self::dispatchedOutbox();
        $policy = (new WorkflowPolicy())->withOutboxRetention(new DateInterval('P2D'));

        static::assertSame(2, self::handler($outbox, $policy)(new PurgeOutbox(null)));
    }

    public function testAnEntryDispatchedExactlyAtTheLimitIsKept(): void
    {
        $outbox = self::dispatchedOutbox();

        static::assertSame(1, self::handler($outbox, new WorkflowPolicy())(new PurgeOutbox(new DateInterval('P3D'))));
    }

    /**
     * Three dispatched entries, at 5, 3 and 1 days before 2026-11-17T09:00:00Z.
     */
    private static function dispatchedOutbox(): InMemoryOutbox
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(
            ...IntegrationEventTranslator::translate(DomainEventSamples::declarationDeclared()),
            ...IntegrationEventTranslator::translate(DomainEventSamples::mandateRegistered()),
            ...IntegrationEventTranslator::translate(DomainEventSamples::declarationAlreadyDeclared()),
        );
        $at = ['2026-11-12T09:00:00Z', '2026-11-14T09:00:00Z', '2026-11-16T09:00:00Z'];
        foreach ($outbox->pending(10) as $index => $entry) {
            $outbox->markDispatched($entry, Instant::fromIso8601($at[$index]));
        }

        return $outbox;
    }

    private static function handler(InMemoryOutbox $outbox, WorkflowPolicy $policy): PurgeOutboxHandler
    {
        return new PurgeOutboxHandler($outbox, new FrozenClock(new DateTimeImmutable('2026-11-17T09:00:00Z')), $policy);
    }
}
