<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;

/**
 * Withholding-at-source part of the computed amounts (`DeclarationCommun`): taxable net salary, rate, tax withheld and
 * net salary payable after withholding. Every field is optional and response amounts are never bounded (ADR 0008).
 */
final readonly class WithholdingTax
{
    private function __construct(
        private ?Amount $taxableNetSalary,
        private ?Rate $rate,
        private ?Amount $taxWithheld,
        private ?Amount $netPayableAfterTax,
    ) {}

    /**
     * @param Amount|null $taxableNetSalary   `mntSalaireNetImposable`
     * @param Rate|null   $rate               `txPas`
     * @param Amount|null $taxWithheld        `mntImpotPas`
     * @param Amount|null $netPayableAfterTax `mntSalaireNetApayerApresPas`
     */
    public static function of(?Amount $taxableNetSalary, ?Rate $rate, ?Amount $taxWithheld, ?Amount $netPayableAfterTax): self
    {
        return new self($taxableNetSalary, $rate, $taxWithheld, $netPayableAfterTax);
    }

    /**
     * `mntSalaireNetImposable`.
     */
    public function taxableNetSalary(): ?Amount
    {
        return $this->taxableNetSalary;
    }

    /**
     * `txPas`.
     */
    public function rate(): ?Rate
    {
        return $this->rate;
    }

    /**
     * `mntImpotPas`.
     */
    public function taxWithheld(): ?Amount
    {
        return $this->taxWithheld;
    }

    /**
     * `mntSalaireNetApayerApresPas`.
     */
    public function netPayableAfterTax(): ?Amount
    {
        return $this->netPayableAfterTax;
    }
}
