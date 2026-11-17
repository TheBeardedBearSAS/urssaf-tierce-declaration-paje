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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * URSSAF accepted the declaration, or the operator found it (state declared). Carries the reference of the volet social and the codes of the non-blocking messages, never an amount.
 */
final readonly class DeclarationDeclared implements DomainEvent
{
    /**
     * @param list<string> $nonBlockingCodes
     */
    public function __construct(
        private DeclarationRecordId $recordId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private ?EmployerAccountId $employerAccountId,
        private DeclarationActivity $activity,
        private DeclarationPeriod $period,
        private ?string $documentReference,
        private array $nonBlockingCodes,
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

    public function documentReference(): ?string
    {
        return $this->documentReference;
    }

    /**
     * @return list<string>
     */
    public function nonBlockingCodes(): array
    {
        return $this->nonBlockingCodes;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
