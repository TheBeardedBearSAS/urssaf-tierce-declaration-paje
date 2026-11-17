<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event;

/**
 * A fact of the PAJE workflow published to the host application through the outbox: scalar properties only, so that
 * any Messenger serializer handles it, and a stable contract versioned independently of the domain model.
 *
 * - eventId: stable identifier of the fact (the same fact always has the same id): deduplicate on it, the relay
 *   delivers at least once;
 * - occurredAt: ISO 8601 instant (UTC) at which the fact happened;
 * - aggregateId: the local id of the employer account, mandate or declaration record;
 * - externalReference: the reference of the record in the host application.
 */
abstract readonly class IntegrationEvent
{
    /**
     * Name of the event type, for example "urssaf.paje.declaration.declared" (ADR 0012).
     *
     * @var string
     */
    public const TYPE = 'urssaf.paje.event';

    /**
     * Version of the payload of the type; incremented on a breaking change of its properties.
     *
     * @var positive-int
     */
    public const VERSION = 1;

    public function __construct(
        public string $eventId,
        public string $occurredAt,
        public string $tenantId,
        public string $aggregateId,
        public string $externalReference,
    ) {}

    final public function type(): string
    {
        return static::TYPE;
    }

    final public function version(): int
    {
        return static::VERSION;
    }

    /**
     * Every property, by name (the named arguments of the constructor).
     *
     * @return array<string, mixed>
     */
    final public function toArray(): array
    {
        // @mago-expect lint:inline-variable-return The variable carries the key type that get_object_vars() loses.
        /** @var array<string, mixed> $properties */
        $properties = get_object_vars($this);

        return $properties;
    }
}
