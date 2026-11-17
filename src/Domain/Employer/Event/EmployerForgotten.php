<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * The employer account, its mandates and its declaration records were erased from the local store (GDPR, ADR 0015). Nothing
 * was sent to URSSAF. Identifiers only: no employer number, no birth date (ADR 0012).
 */
final readonly class EmployerForgotten implements DomainEvent
{
    /**
     * @param list<MandateId>           $mandateIds
     * @param list<DeclarationRecordId> $declarationRecordIds
     */
    public function __construct(
        private EmployerAccountId $accountId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private array $mandateIds,
        private array $declarationRecordIds,
        private Instant $occurredAt,
    ) {}

    public function accountId(): EmployerAccountId
    {
        return $this->accountId;
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
     * @return list<MandateId>
     */
    public function mandateIds(): array
    {
        return $this->mandateIds;
    }

    /**
     * @return list<DeclarationRecordId>
     */
    public function declarationRecordIds(): array
    {
        return $this->declarationRecordIds;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
