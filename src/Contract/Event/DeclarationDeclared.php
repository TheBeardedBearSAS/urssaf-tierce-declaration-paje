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
 * URSSAF recorded the declaration (method 050 / 051 success, or manual resolution of an unknown outcome, ADR 0003).
 *
 * - aggregateId: the declaration record id;
 * - employerAccountId: the id of the tracked employer account, null when the employer is not tracked;
 * - activityType: "childminder" or "home_care";
 * - periodStart, periodEnd: the service period, as yyyy-MM-dd;
 * - referenceDocumentaire: the URSSAF reference of the declaration, null when the answer carried none (never an empty
 *   string);
 * - nonBlockingCodes: the codes of the non-blocking messages of the answer, never their text.
 */
final readonly class DeclarationDeclared extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.declaration.declared';

    /**
     * @param list<string> $nonBlockingCodes
     */
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
        public ?string $referenceDocumentaire,
        public array $nonBlockingCodes,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
