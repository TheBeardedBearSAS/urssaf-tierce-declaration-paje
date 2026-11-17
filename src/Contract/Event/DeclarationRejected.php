<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event;

/**
 * URSSAF refused the declaration and recorded nothing (final, ADR 0006).
 *
 * - aggregateId: the declaration record id;
 * - employerAccountId: the id of the tracked employer account, null when the employer is not tracked;
 * - activityType: "childminder" or "home_care";
 * - periodStart, periodEnd: the service period, as yyyy-MM-dd.
 * - errorCodes: the URSSAF error codes, never their description.
 */
final readonly class DeclarationRejected extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.declaration.rejected';

    /**
     * @param list<string> $errorCodes
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
        public array $errorCodes,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
