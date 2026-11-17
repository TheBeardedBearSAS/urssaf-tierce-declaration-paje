<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Contract\Event;

use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;

/**
 * Test double: an integration event with one extra property, its own type and version.
 */
final readonly class SampleIntegrationEvent extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.sample';
    public const VERSION = 2;

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $state,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
