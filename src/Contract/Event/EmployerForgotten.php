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
 * The employer account, its mandates and its declaration records were erased from the package store (GDPR,
 * ForgetEmployer, ADR 0015): erase your copies. Nothing was erased at URSSAF.
 *
 * - aggregateId: the (erased) employer account id;
 * - mandateIds: the erased mandate ids (aggregateId of the mandate events), oldest first;
 * - declarationRecordIds: the erased declaration record ids (aggregateId of the declaration events), oldest first.
 */
final readonly class EmployerForgotten extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.employer.forgotten';

    /**
     * @param list<string> $mandateIds
     * @param list<string> $declarationRecordIds
     */
    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public array $mandateIds,
        public array $declarationRecordIds,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
