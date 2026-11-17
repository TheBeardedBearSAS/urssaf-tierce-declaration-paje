<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * The mandate is registered (method 030 succeeded, or an existing mandate was imported). Identifiers only (ADR 0012).
 */
final readonly class MandateRegistered implements DomainEvent
{
    public function __construct(
        private MandateId $mandateId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private EmployerAccountId $employerAccountId,
        private MandateOrigin $origin,
        private Instant $occurredAt,
    ) {}

    public function mandateId(): MandateId
    {
        return $this->mandateId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    public function employerAccountId(): EmployerAccountId
    {
        return $this->employerAccountId;
    }

    public function origin(): MandateOrigin
    {
        return $this->origin;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
