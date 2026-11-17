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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * An employer account is tracked (by a use case of this package, or imported). Identifiers only: no employer number, no birth
 * date (ADR 0007, 0012).
 */
final readonly class EmployerRegistered implements DomainEvent
{
    public function __construct(
        private EmployerAccountId $accountId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private EmployerOrigin $origin,
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

    public function origin(): EmployerOrigin
    {
        return $this->origin;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
