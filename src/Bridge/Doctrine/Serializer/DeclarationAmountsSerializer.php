<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;

/**
 * Explicit JSON shape of the amounts of an accepted declaration (a part of the result document): amounts in cents, the
 * withholding rate as its decimal string, dates as "Y-m-d". Personal data of the employee (ADR 0007).
 *
 * @phpstan-type WithholdingData array{taxableNetSalary: int|null, rate: string|null, taxWithheld: int|null, netPayableAfterTax: int|null}
 * @phpstan-type AmountsData array{contributions: int|null, coveredByFamilyBenefits: int|null, employerShare: int|null, withholding: WithholdingData, cmgRemuneration: int|null, payableToEmployee: int|null, payableByPaje: int|null, debitedByPaje: int|null, plannedDebitDate: string|null, plannedPaymentDate: string|null}
 */
final class DeclarationAmountsSerializer
{
    /**
     * @return AmountsData
     */
    public static function toArray(DeclarationAmounts $amounts): array
    {
        $withholding = $amounts->withholding();

        return [
            'contributions' => self::cents($amounts->contributions()),
            'coveredByFamilyBenefits' => self::cents($amounts->coveredByFamilyBenefits()),
            'employerShare' => self::cents($amounts->employerShare()),
            'withholding' => [
                'taxableNetSalary' => self::cents($withholding->taxableNetSalary()),
                'rate' => $withholding->rate()?->toString(),
                'taxWithheld' => self::cents($withholding->taxWithheld()),
                'netPayableAfterTax' => self::cents($withholding->netPayableAfterTax()),
            ],
            'cmgRemuneration' => self::cents($amounts->cmgRemuneration()),
            'payableToEmployee' => self::cents($amounts->payableToEmployee()),
            'payableByPaje' => self::cents($amounts->payableByPaje()),
            'debitedByPaje' => self::cents($amounts->debitedByPaje()),
            'plannedDebitDate' => $amounts->plannedDebitDate()?->toString(),
            'plannedPaymentDate' => $amounts->plannedPaymentDate()?->toString(),
        ];
    }

    /**
     * @param AmountsData $data
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): DeclarationAmounts
    {
        $withholding = $data['withholding'];

        return DeclarationAmounts::of(
            self::amount($data['contributions']),
            self::amount($data['coveredByFamilyBenefits']),
            self::amount($data['employerShare']),
            WithholdingTax::of(
                self::amount($withholding['taxableNetSalary']),
                $withholding['rate'] === null ? null : Rate::fromString($withholding['rate']),
                self::amount($withholding['taxWithheld']),
                self::amount($withholding['netPayableAfterTax']),
            ),
            self::amount($data['cmgRemuneration']),
            self::amount($data['payableToEmployee']),
            self::amount($data['payableByPaje']),
            self::amount($data['debitedByPaje']),
            self::date($data['plannedDebitDate']),
            self::date($data['plannedPaymentDate']),
        );
    }

    private static function cents(?Amount $amount): ?int
    {
        return $amount?->cents();
    }

    private static function amount(?int $cents): ?Amount
    {
        return $cents === null ? null : Amount::fromCents($cents);
    }

    /**
     * @throws InvalidValue
     */
    private static function date(?string $date): ?CalendarDate
    {
        return $date === null ? null : CalendarDate::fromString($date);
    }
}
