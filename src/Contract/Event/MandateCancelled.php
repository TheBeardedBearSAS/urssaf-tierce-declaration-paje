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
 * The mandate was cancelled (method 031, ADR 0006).
 *
 * - aggregateId: the mandate id;
 * - externalReference: the reference of the employer in the host application;
 * - employerAccountId: the id of the employer account.
 */
final readonly class MandateCancelled extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.mandate.cancelled';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $employerAccountId,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
