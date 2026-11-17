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
 * The outcome of the declaration is unknown: it was never resent and needs a manual review (ADR 0003).
 *
 * - aggregateId: the declaration record id;
 * - employerAccountId: the id of the tracked employer account, null when the employer is not tracked;
 * - activityType: "childminder" or "home_care";
 * - periodStart, periodEnd: the service period, as yyyy-MM-dd.
 * - reason: "may_have_reached_urssaf" or "stale_submission".
 */
final readonly class DeclarationOutcomeUnknown extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.declaration.outcome_unknown';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public ?string $employerAccountId,
        public string $activityType,
        public string $periodStart,
        public string $periodEnd,
        public string $reason,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
