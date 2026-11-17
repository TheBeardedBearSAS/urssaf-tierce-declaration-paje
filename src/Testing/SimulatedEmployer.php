<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Testing;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * An employer account of the simulated Pajemploi registry: online account state, mandate holder, entitled children and
 * associated employees.
 *
 * @internal
 */
final class SimulatedEmployer
{
    private ?TenantId $mandateHolder = null;

    private string $onlineAccountState = 'Actif';

    /** @var list<string> keys of the entitled children */
    private array $entitledChildren = [];

    /** @var list<string> numbers of the associated employees */
    private array $associatedEmployees = [];

    public function __construct(private readonly EmployerReference $reference) {}

    /**
     * Same Pajemploi number and same birth date.
     */
    public function matches(EmployerReference $employer): bool
    {
        return $this->reference->equals($employer);
    }

    public function holdBy(?TenantId $tenantId): void
    {
        $this->mandateHolder = $tenantId;
    }

    public function isMandatedBy(TenantId $tenantId): bool
    {
        return $this->mandateHolder?->equals($tenantId) ?? false;
    }

    /**
     * Whether another tenant than the given one holds the mandate.
     */
    public function isHeldByAnotherThan(TenantId $tenantId): bool
    {
        return $this->mandateHolder instanceof TenantId && !$this->mandateHolder->equals($tenantId);
    }

    public function setOnlineAccountState(string $state): void
    {
        $this->onlineAccountState = $state;
    }

    public function onlineAccountState(): string
    {
        return $this->onlineAccountState;
    }

    public function entitle(Child $child): void
    {
        $this->entitledChildren[] = SimulatedKey::child($child);
    }

    public function isEntitled(Child $child): bool
    {
        return \in_array(SimulatedKey::child($child), $this->entitledChildren, true);
    }

    public function associate(SimulatedEmployee $employee): void
    {
        $this->associatedEmployees[] = $employee->number;
    }

    public function isAssociated(SimulatedEmployee $employee): bool
    {
        return \in_array($employee->number, $this->associatedEmployees, true);
    }
}
