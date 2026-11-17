<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * The submission failed and is proven not sent (state error): it may be retried.
 */
final readonly class DeclarationSubmissionFailed implements DomainEvent
{
    /**
     * @param list<string> $errorCodes codes only: the URSSAF messages may echo personal data (ADR 0007)
     */
    public function __construct(
        private DeclarationRecordId $recordId,
        private TenantId $tenantId,
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
