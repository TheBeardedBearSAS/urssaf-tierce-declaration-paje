<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Port;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Contract\Event\SampleIntegrationEvent;

final class PortValuesTest extends TestCase
{
    public function testOutboxEntry(): void
    {
        $event = new SampleIntegrationEvent('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'local-1', 'ref-1', 'declared');

        $entry = new OutboxEntry(7, $event, 2);

        static::assertSame(7, $entry->sequence);
        static::assertSame($event, $entry->event);
        static::assertSame(2, $entry->attempts);
    }

    public function testStateCount(): void
    {
        $tenantId = TenantId::fromString('tenant-1');

        $count = new StateCount($tenantId, 'declared', 5);

        static::assertSame($tenantId, $count->tenantId);
        static::assertSame('declared', $count->state);
        static::assertSame(5, $count->count);
    }
}
