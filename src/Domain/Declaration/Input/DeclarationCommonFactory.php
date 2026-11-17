<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw `InputDeclCommun` of a declaration into a {@see DeclarationCommon}, reporting every violation
 * (notification pattern, ADR 0000 item 4). The rules are those of {@see DeclarationCommon}.
 *
 * Input keys: "period_start", "period_end", "payment_date" (Y-m-d, required), "hours" (integer, required),
 * "paid_leave_days" (decimal days, a multiple of 0.5), "net_hourly_rate", "specific_hours" (integer),
 * "employee_advance", "net_monthly_salary" (required), "mileage_allowance" (decimal strings).
 */
final readonly class DeclarationCommonFactory
{
    /**
     * @param array<array-key, mixed> $input
     */
    public function validate(#[SensitiveParameter] array $input): ValidationResult
    {
        return RawFields::validate($input, $this->parse(...));
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function fromInput(#[SensitiveParameter] array $input): DeclarationCommon
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    /**
     * @internal
     */
    public function parse(RawFields $fields): ?DeclarationCommon
    {
        $errorsBefore = $fields->errorCount();
        $start = $fields->required('period_start', CalendarDate::fromString(...));
        $end = $fields->required('period_end', CalendarDate::fromString(...));
        $paymentDate = $fields->required('payment_date', CalendarDate::fromString(...));
        $hours = $fields->requiredInteger('hours');
        $paidLeaveDays = $fields->optional('paid_leave_days', HalfDays::fromDecimalString(...));
        $netHourlyRate = $fields->optional('net_hourly_rate', Amount::fromDecimalString(...));
        $specificHours = $fields->integer('specific_hours');
        $employeeAdvance = $fields->optional('employee_advance', Amount::fromDecimalString(...));
        $netMonthlySalary = $fields->required('net_monthly_salary', Amount::fromDecimalString(...));
        $mileageAllowance = $fields->optional('mileage_allowance', Amount::fromDecimalString(...));
        $common = $fields->build('', static fn(): DeclarationCommon => DeclarationCommon::of(
            DeclarationPeriod::of(
                $start ?? DeclarationPlaceholders::date(),
                $end ?? DeclarationPlaceholders::date(),
                $paymentDate ?? DeclarationPlaceholders::date(),
            ),
            $hours ?? DeclarationPlaceholders::HOURS,
            $paidLeaveDays,
            $netHourlyRate,
            $specificHours,
            $employeeAdvance,
            $netMonthlySalary ?? DeclarationPlaceholders::amount(),
            $mileageAllowance,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $common;
    }
}
