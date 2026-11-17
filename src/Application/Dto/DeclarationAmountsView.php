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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Read model of the amounts URSSAF computed for a declaration or a pre-declaration (`DeclarationCommun`): amounts as
 * decimal euro strings with two decimals, dates as `yyyy-MM-dd`; null when URSSAF did not return the value. The
 * withholding-at-source part is a {@see WithholdingTaxView}.
 */
final readonly class DeclarationAmountsView
{
    private function __construct(
        public ?string $contributions,
        public ?string $coveredByFamilyBenefits,
        public ?string $employerShare,
        public WithholdingTaxView $withholding,
        public ?string $cmgRemuneration,
        public ?string $payableToEmployee,
        public ?string $payableByPaje,
        public ?string $debitedByPaje,
        public ?string $plannedDebitDate,
        public ?string $plannedPaymentDate,
    ) {}

    public static function fromAmounts(DeclarationAmounts $amounts): self
    {
        return new self(
            self::amount($amounts->contributions()),
            self::amount($amounts->coveredByFamilyBenefits()),
            self::amount($amounts->employerShare()),
            WithholdingTaxView::fromWithholding($amounts->withholding()),
            self::amount($amounts->cmgRemuneration()),
            self::amount($amounts->payableToEmployee()),
            self::amount($amounts->payableByPaje()),
            self::amount($amounts->debitedByPaje()),
            self::date($amounts->plannedDebitDate()),
            self::date($amounts->plannedPaymentDate()),
        );
    }

    private static function amount(?Amount $amount): ?string
    {
        return $amount?->toDecimalString();
    }

    private static function date(?CalendarDate $date): ?string
    {
        return $date?->toString();
    }
}
