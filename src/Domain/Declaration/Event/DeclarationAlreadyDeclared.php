<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * URSSAF reported a duplicate: it already holds the declaration (state already_declared, final).
 */
final readonly class DeclarationAlreadyDeclared implements DomainEvent
{
    /**
     * @param list<string> $errorCodes codes only: the URSSAF messages may echo personal data (ADR 0007)
     */
    public function __construct(
        private DeclarationRecordId $recordId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private ?EmployerAccountId $employerAccountId,
        private DeclarationActivity $activity,
        private DeclarationPeriod $period,
        private array $errorCodes,
        private Instant $occurredAt,
    ) {}

    public function recordId(): DeclarationRecordId
    {
        return $this->recordId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    /**
     * The tracked employer account of the record when it was recorded, null when the employer was not tracked.
     */
    public function employerAccountId(): ?EmployerAccountId
    {
        return $this->employerAccountId;
    }

    public function activity(): DeclarationActivity
    {
        return $this->activity;
    }

    public function period(): DeclarationPeriod
    {
        return $this->period;
    }

    /**
     * @return list<string>
     */
    public function errorCodes(): array
    {
        return $this->errorCodes;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
