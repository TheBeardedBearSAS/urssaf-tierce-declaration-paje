<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;

/**
 * Read model of the withholding-at-source part of the computed amounts: amounts as decimal euro strings with two
 * decimals, the rate (`txPas`) as text; null when URSSAF did not return the value.
 */
final readonly class WithholdingTaxView
{
    private function __construct(
        public ?string $taxableNetSalary,
        public ?string $rate,
        public ?string $taxWithheld,
        public ?string $netPayableAfterTax,
    ) {}

    public static function fromWithholding(WithholdingTax $withholding): self
    {
        return new self(
            $withholding->taxableNetSalary()?->toDecimalString(),
            $withholding->rate()?->toString(),
            $withholding->taxWithheld()?->toDecimalString(),
            $withholding->netPayableAfterTax()?->toDecimalString(),
        );
    }
}
