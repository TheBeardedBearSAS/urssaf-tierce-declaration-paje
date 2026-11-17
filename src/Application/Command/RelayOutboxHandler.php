<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\RelayReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use Throwable;

/**
 * At-least-once delivery of the outbox: an event is marked as dispatched only once the sink accepted it. A failed event
 * stays pending, and the next events of the same aggregate are not delivered in this run, so that the host receives the
 * events of a record in order. Runs must not overlap (the Symfony bridge takes a lock).
 *
 * A failure is stored as the class and the code of the sink exception only, never its message: a host sink may put
 * personal data in it (ADR 0007).
 */
final readonly class RelayOutboxHandler
{
    public function __construct(
        private Outbox $outbox,
        private IntegrationEventSink $sink,
        private Clock $clock,
        private WorkflowPolicy $policy,
    ) {}

    public function __invoke(RelayOutbox $command): RelayReport
    {
        $dispatched = 0;
        $failed = 0;
        $deferred = 0;
        $blocked = [];
        foreach ($this->outbox->pending($this->policy->pageSize($command->limit)) as $entry) {
            $aggregate = $entry->event->aggregateId;
            if (\array_key_exists($aggregate, $blocked)) {
                ++$deferred;

                continue;
            }

            try {
                $this->sink->dispatch($entry->event);
            } catch (Throwable $failure) {
                $this->outbox->markFailed($entry, \sprintf('%s (code %s)', $failure::class, $failure->getCode()));
                $blocked[$aggregate] = $entry->sequence;
                ++$failed;

                continue;
            }
            $this->outbox->markDispatched($entry, Instant::fromDateTime($this->clock->now()));
            ++$dispatched;
        }

        return new RelayReport($dispatched, $failed, $deferred);
    }
}
