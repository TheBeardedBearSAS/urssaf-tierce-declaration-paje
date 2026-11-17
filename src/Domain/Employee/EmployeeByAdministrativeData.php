<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Employee identified by its administrative data (priority 3), with the optional birth date of the query
 * (`dtNaissanceSalarie`). Not a {@see DeclaredEmployee}: a declaration cannot carry it. Personal data: masked in debug
 * output.
 *
 * The identity is the query criterion of operation 021 (body) and must set at least one field (api-reference §4
 * decision 10): an empty identity would send a `{}` body. On operation 023 the wire has a single slot for the identity
 * (`inputCreationSalarie.inputIdentificationSalarie`), read from {@see EmployeeCreation::identity()} only; the identity
 * held here is never sent on 023 (it is not a second source, so the two cannot conflict).
 */
final readonly class EmployeeByAdministrativeData implements EmployeeIdentification
{
    private function __construct(
        private AdministrativeIdentity $identity,
        #[SensitiveParameter]
        private ?CalendarDate $birthDate,
    ) {}

    /**
     * @throws InvalidValue "employee_identification.empty" when the identity sets no field
     */
    public static function of(AdministrativeIdentity $identity, #[SensitiveParameter] ?CalendarDate $birthDate = null): self
    {
        if ($identity->isEmpty()) {
            throw InvalidValue::because('employee_identification.empty', 'Invalid employee_identification: at least one criterion is required.');
        }

        return new self($identity, $birthDate);
    }

    public function kind(): EmployeeIdentificationKind
    {
        return EmployeeIdentificationKind::AdministrativeData;
    }

    public function identity(): AdministrativeIdentity
    {
        return $this->identity;
    }

    public function birthDate(): ?CalendarDate
    {
        return $this->birthDate;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
