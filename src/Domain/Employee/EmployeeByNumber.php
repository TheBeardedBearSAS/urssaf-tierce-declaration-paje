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
 * Employee identified by its Pajemploi number (priority 1), with the optional birth date of the query (`dtNaissanceSalarie`).
 * Personal data: masked in debug output.
 */
final readonly class EmployeeByNumber implements DeclaredEmployee
{
    private function __construct(
        private PajeEmployeeNumber $number,
        #[SensitiveParameter]
        private ?CalendarDate $birthDate,
    ) {}

    public static function of(PajeEmployeeNumber $number, #[SensitiveParameter] ?CalendarDate $birthDate = null): self
    {
        return new self($number, $birthDate);
    }

    public function kind(): EmployeeIdentificationKind
    {
        return EmployeeIdentificationKind::Number;
    }

    public function number(): PajeEmployeeNumber
    {
        return $this->number;
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
