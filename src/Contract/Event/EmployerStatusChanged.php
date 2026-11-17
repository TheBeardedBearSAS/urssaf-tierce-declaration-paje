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
 * The tracked state of an employer changed: a method 020 refresh, or a local mandate change (030 / 031, ADR 0006,
 * ADR 0012).
 *
 * - aggregateId: the employer account id;
 * - previousStage, stage: the local employer stages (tracked, mandate_registered, mandate_cancelled);
 * - authorised, onlineAccountState: the raw 020 answer (employeurAutoriseOk, etatCompteEnLigne), null when the change
 *   comes from a local mandate change (no 020 answer). The Pajemploi number and the birth date are never published.
 */
final readonly class EmployerStatusChanged extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.employer.status_changed';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $previousStage,
        public string $stage,
        public ?bool $authorised,
        public ?string $onlineAccountState,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
