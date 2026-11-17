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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\AmountRules;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Data shared by the four declaration operations (`InputDeclCommun`, api-reference §2.7), with the Swagger bounds of
 * every field. Hours have no maximum (the laxer bound, §4 decision 11); the net monthly salary must be strictly
 * positive.
 *
 * Violation paths (English property names): "hours", "paidLeaveDays", "netHourlyRate", "specificHours",
 * "employeeAdvance", "netMonthlySalary", "mileageAllowance".
 */
final readonly class DeclarationCommon
{
    private const MIN_HOURS = 1;
    private const MAX_HOURS = 2_147_483_647; // Swagger `format: int32`, the only bound of `nbHeures` (ADR 0009)
    private const MAX_SPECIFIC_HOURS = 1000;
    private const MAX_PAID_LEAVE_HALF_DAYS = 198;

    private function __construct(
        private DeclarationPeriod $period,
        private int $hours,
        private ?HalfDays $paidLeaveDays,
        private ?Amount $netHourlyRate,
        private ?int $specificHours,
        private ?Amount $employeeAdvance,
        private Amount $netMonthlySalary,
        private ?Amount $mileageAllowance,
    ) {}

    /**
     * @param int           $hours            `nbHeures`, paid-leave and specific hours included, 1 to the int32 maximum
     * @param HalfDays|null $paidLeaveDays    `nbJoursCongesPayes`, at most 99
     * @param Amount|null   $netHourlyRate    `mntSalaireHoraireNet`
     * @param int|null      $specificHours    `nbHeuresSpecifiques`, 0 to 1000
     * @param Amount|null   $employeeAdvance  `mntAcompteSalarie`
     * @param Amount        $netMonthlySalary `mntSalaireNetMensuel`, strictly positive
     * @param Amount|null   $mileageAllowance `mntIndemnitesKilometriques`
     *
     * @throws ValidationFailed with every violation
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        DeclarationPeriod $period,
        int $hours,
        ?HalfDays $paidLeaveDays,
        ?Amount $netHourlyRate,
        ?int $specificHours,
        ?Amount $employeeAdvance,
        Amount $netMonthlySalary,
        ?Amount $mileageAllowance,
    ): self {
        $collector = new ViolationCollector();
        $collector->optionalInteger('hours', 'hours', $hours, self::MIN_HOURS, self::MAX_HOURS);
        if ($paidLeaveDays instanceof HalfDays) {
            $collector->attempt('paidLeaveDays', static fn(): HalfDays => $paidLeaveDays->assertAtMost(
                HalfDays::fromHalfDays(self::MAX_PAID_LEAVE_HALF_DAYS),
                'paid_leave_days',
            ));
        }
        AmountRules::capped($collector, 'netHourlyRate', 'net_hourly_rate', $netHourlyRate);
        $collector->optionalInteger('specificHours', 'specific_hours', $specificHours, 0, self::MAX_SPECIFIC_HOURS);
        AmountRules::capped($collector, 'employeeAdvance', 'employee_advance', $employeeAdvance);
        AmountRules::positiveCapped($collector, 'netMonthlySalary', 'net_monthly_salary', $netMonthlySalary);
        AmountRules::capped($collector, 'mileageAllowance', 'mileage_allowance', $mileageAllowance);
        $collector->result()->throwIfInvalid();

        return new self(
            $period,
            $hours,
            $paidLeaveDays,
            $netHourlyRate,
            $specificHours,
            $employeeAdvance,
            $netMonthlySalary,
            $mileageAllowance,
        );
    }

    public function period(): DeclarationPeriod
    {
        return $this->period;
    }

    /**
     * `nbHeures`.
     */
    public function hours(): int
    {
        return $this->hours;
    }

    /**
     * `nbJoursCongesPayes`.
     */
    public function paidLeaveDays(): ?HalfDays
    {
        return $this->paidLeaveDays;
    }

    /**
     * `mntSalaireHoraireNet`.
     */
    public function netHourlyRate(): ?Amount
    {
        return $this->netHourlyRate;
    }

    /**
     * `nbHeuresSpecifiques`.
     */
    public function specificHours(): ?int
    {
        return $this->specificHours;
    }

    /**
     * `mntAcompteSalarie`.
     */
    public function employeeAdvance(): ?Amount
    {
        return $this->employeeAdvance;
    }

    /**
     * `mntSalaireNetMensuel`.
     */
    public function netMonthlySalary(): Amount
    {
        return $this->netMonthlySalary;
    }

    /**
     * `mntIndemnitesKilometriques`.
     */
    public function mileageAllowance(): ?Amount
    {
        return $this->mileageAllowance;
    }
}
