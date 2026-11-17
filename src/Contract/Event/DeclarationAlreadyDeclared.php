<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event;

/**
 * URSSAF answered that it already holds the declaration (final, ADR 0003, ADR 0006): neither declared nor rejected by
 * this package, the host must act on it.
 *
 * - aggregateId: the declaration record id;
 * - employerAccountId: the id of the tracked employer account, null when the employer is not tracked;
 * - activityType: "childminder" or "home_care";
 * - periodStart, periodEnd: the service period, as yyyy-MM-dd.
 * - errorCodes: the URSSAF error codes of the duplicate answer (ER_API_050_0070, ER_API_DECLA_00020).
 */
final readonly class DeclarationAlreadyDeclared extends IntegrationEvent
{
    public const TYPE = 'urssaf.paje.declaration.already_declared';

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
