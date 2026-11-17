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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Body of operation 023 `InputCreationSalarie` (api-reference §2.4): the data used to create the employee when the
 * server does not know it. Every field is optional (Swagger); the PDF-only rules (activity type, birth date, address
 * line, `dtDebutAgrement` for a childminder) are left to the server (ADR 0009). An empty creation is sent as `{}`
 * (api-reference §4 decision 9). The identity is the only source of `inputCreationSalarie.inputIdentificationSalarie` on
 * operation 023: the identity of an {@see EmployeeByAdministrativeData} identification is a query criterion and is never
 * copied into the creation body. Personal data: masked in debug output.
 */
final readonly class EmployeeCreation
{
    private function __construct(
        private ?ActivityType $activityType,
        #[SensitiveParameter]
        private ?CalendarDate $birthDate,
        private ?Nir $nir,
        #[SensitiveParameter]
        private ?CalendarDate $licenceStartDate,
        private ?Address $address,
        private ?AdministrativeIdentity $identity,
    ) {}

    /**
     * @param CalendarDate|null $licenceStartDate `dtDebutAgrement`
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        ?ActivityType $activityType,
        #[SensitiveParameter]
        ?CalendarDate $birthDate,
        ?Nir $nir,
        #[SensitiveParameter]
        ?CalendarDate $licenceStartDate,
        ?Address $address,
        ?AdministrativeIdentity $identity,
    ): self {
        return new self($activityType, $birthDate, $nir, $licenceStartDate, $address, $identity);
    }

    public function activityType(): ?ActivityType
    {
        return $this->activityType;
    }

    public function birthDate(): ?CalendarDate
    {
        return $this->birthDate;
    }

    public function nir(): ?Nir
    {
        return $this->nir;
    }

    public function licenceStartDate(): ?CalendarDate
    {
        return $this->licenceStartDate;
    }

    public function address(): ?Address
    {
        return $this->address;
    }

    public function identity(): ?AdministrativeIdentity
    {
        return $this->identity;
    }

    /**
     * True when no field is set: the body is then `{}`.
     */
    public function isEmpty(): bool
    {
        return !$this->activityType instanceof ActivityType
            && !$this->birthDate instanceof CalendarDate
            && !$this->nir instanceof Nir
            && !$this->licenceStartDate instanceof CalendarDate
            && !$this->address instanceof Address
            && !$this->identity instanceof AdministrativeIdentity;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
