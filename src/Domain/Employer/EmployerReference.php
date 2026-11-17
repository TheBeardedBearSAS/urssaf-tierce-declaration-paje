<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Employer context shared by every employer-scoped operation (api-reference §2, §4 decision 2): the Pajemploi number
 * (`numeroPajeEmployeur`) and the birth date (`dtNaissanceEmployeur`). A credential-like pair, personal data: masked
 * in debug output, no string conversion (ADR 0007).
 */
final readonly class EmployerReference
{
    private function __construct(
        private PajeEmployerNumber $number,
        #[SensitiveParameter]
        private CalendarDate $birthDate,
    ) {}

    public static function of(PajeEmployerNumber $number, #[SensitiveParameter] CalendarDate $birthDate): self
    {
        return new self($number, $birthDate);
    }

    public function number(): PajeEmployerNumber
    {
        return $this->number;
    }

    public function birthDate(): CalendarDate
    {
        return $this->birthDate;
    }

    public function equals(self $other): bool
    {
        return $this->number->equals($other->number) && $this->birthDate->equals($other->birthDate);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
