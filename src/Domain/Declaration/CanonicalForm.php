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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;

/**
 * Canonical text of a declaration, hashed into its fingerprint (ADR 0006 "same content", checked by a fingerprint that
 * excludes personal data): one `key=value` line per field, absent values written "-", amounts in cents, half-days as
 * integers.
 *
 * It holds the activity type, the period and payment dates, the hours, days, amounts and indemnities, the calculation
 * mode (home care) and, per child, the care day counts. It never holds personal data: no NIR, name, birth date, employee
 * number, employee identification kind or child identity.
 *
 * @internal
 */
final class CanonicalForm
{
    private const ABSENT = '-';

    public static function fingerprint(Declaration $declaration): string
    {
        return hash('sha256', self::of($declaration));
    }

    public static function of(Declaration $declaration): string
    {
        return implode("\n", [
            'activity=' . $declaration->activityType()->value,
            ...self::commonLines($declaration->common()),
            'children_count=' . \count($declaration->children()),
            ...match (true) {
                $declaration instanceof ChildminderDeclaration => self::childminderLines($declaration),
                $declaration instanceof HomeCareDeclaration => self::homeCareLines($declaration),
                default => [],
            },
        ]);
    }

    /**
     * @return list<string>
     */
    private static function commonLines(DeclarationCommon $common): array
    {
        $period = $common->period();

        return [
            'start_date=' . $period->start()->toString(),
            'end_date=' . $period->end()->toString(),
            'payment_date=' . $period->paymentDate()->toString(),
            'hours=' . $common->hours(),
            'paid_leave_half_days=' . self::halfDays($common->paidLeaveDays()),
            'net_hourly_rate=' . self::amount($common->netHourlyRate()),
            'specific_hours=' . self::text($common->specificHours()),
            'employee_advance=' . self::amount($common->employeeAdvance()),
            'net_monthly_salary=' . self::amount($common->netMonthlySalary()),
            'mileage_allowance=' . self::amount($common->mileageAllowance()),
        ];
    }

    /**
     * @return list<string>
     */
    private static function childminderLines(ChildminderDeclaration $declaration): array
    {
        $contractEnd = $declaration->contractEnd();

        return [
            'activity_days=' . $declaration->activityDays(),
            'majored_hours=' . self::text($declaration->majoredHours()),
            'complementary_hours=' . self::text($declaration->complementaryHours()),
            'maintenance_allowance=' . self::amount($declaration->maintenanceAllowance()),
            'meal_allowance=' . self::amount($declaration->mealAllowance()),
            ...self::childLines('child', $declaration->children()),
            ...($contractEnd instanceof ChildminderContractEnd ? [
                'retirement_indemnity=' . self::amount($contractEnd->retirementIndemnity()),
                'paid_leave_compensation=' . self::amount($contractEnd->paidLeaveCompensation()),
                'notice_indemnity=' . self::amount($contractEnd->noticeIndemnity()),
                'precarity_indemnity=' . self::amount($contractEnd->precarityIndemnity()),
                'termination_indemnity=' . self::amount($contractEnd->terminationIndemnity()),
                ...self::childLines('contract_end_child', $contractEnd->children()),
            ] : ['contract_end=' . self::ABSENT]),
        ];
    }

    /**
     * @return list<string>
     */
    private static function homeCareLines(HomeCareDeclaration $declaration): array
    {
        $contractEnd = $declaration->contractEnd();

        return [
            'calculation_mode=' . $declaration->calculationMode()->value(),
            'transport_costs=' . self::amount($declaration->transportCosts()),
            'overtime_hours_25=' . self::text($declaration->overtimeHours25()),
            'overtime_hours_50=' . self::text($declaration->overtimeHours50()),
            ...($contractEnd instanceof HomeCareContractEnd ? [
                'retirement_indemnity=' . self::amount($contractEnd->retirementIndemnity()),
                'paid_leave_compensation=' . self::amount($contractEnd->paidLeaveCompensation()),
                'notice_indemnity=' . self::amount($contractEnd->noticeIndemnity()),
                'precarity_indemnity=' . self::amount($contractEnd->precarityIndemnity()),
                'dismissal_indemnity=' . self::amount($contractEnd->dismissalIndemnity()),
            ] : ['contract_end=' . self::ABSENT]),
        ];
    }

    /**
     * Care day counts of the childminder's children (the child identity is never part of the canonical form).
     *
     * @param list<ChildminderChild> $children
     *
     * @return list<string>
     */
    private static function childLines(string $prefix, array $children): array
    {
        $lines = [];
        foreach ($children as $index => $child) {
            $lines[] = \sprintf(
                '%s[%d]=%s,%s',
                $prefix,
                $index,
                self::text($child->disabledChildCareDays()),
                self::text($child->consecutive24hDays()),
            );
        }

        return $lines;
    }

    private static function amount(?Amount $amount): string
    {
        return self::text($amount?->cents());
    }

    private static function halfDays(?HalfDays $days): string
    {
        return self::text($days?->halfDays());
    }

    private static function text(int|string|null $value): string
    {
        return $value === null ? self::ABSENT : (string) $value;
    }
}
