<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event;

/**
 * A mandate is registered: after method 030, or imported without any URSSAF call (ADR 0006).
 *
 * - aggregateId: the mandate id;
 * - externalReference: the reference of the employer in the host application;
 * - employerAccountId: the id of the employer account;
 * - origin: "registered" (030) or "imported".
 */
final readonly class MandateRegistered extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.mandate.registered';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $employerAccountId,
        public string $origin,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
