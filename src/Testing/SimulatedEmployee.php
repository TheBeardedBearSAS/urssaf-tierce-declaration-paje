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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;

/**
 * An employee of the simulated Pajemploi repository: its number, its childcare modes and the keys it is found by.
 *
 * @internal
 */
final readonly class SimulatedEmployee
{
    /**
     * @param list<string> $keys {@see SimulatedKey} of the identifications that find the employee
     */
    public function __construct(
        public string $number,
        private bool $childminder,
        private bool $homeCare,
        private array $keys,
    ) {}

    public function isFoundBy(string $key): bool
    {
        return \in_array($key, $this->keys, true);
    }

    public function account(): EmployeeAccount
    {
        return EmployeeAccount::of($this->number, $this->childminder, $this->homeCare, OnlineAccountState::of('Actif'));
    }
}
