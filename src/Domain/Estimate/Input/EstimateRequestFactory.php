<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw data of a contribution estimate (operations 040 and 041) into an {@see EstimateRequest}, reporting
 * every violation (notification pattern, ADR 0000 item 4). The rules are those of the aggregates. The activity is the
 * operation (`/ama/...` or `/ged/...`), chosen by the entry point, never a field of the input.
 *
 * Common input keys: "employer_postal_code", "employee_postal_code" (optional), "net_monthly_salary" (decimal string,
 * required).
 *
 * Childminder keys: "children_count", "activity_days" (integers, required), "paid_leave_days" (decimal days, a multiple
 * of 0.5), "majored_hours", "complementary_hours" (integers).
 *
 * Home care keys: "calculation_mode", "youngest_child_age" (codes, required), "hours" (integer, required),
 * "overtime_hours_25", "overtime_hours_50" (integers), "activity_supplement" (boolean, required).
 */
final readonly class EstimateRequestFactory
{
    private const MISSING_SALARY_CENTS = 1;
    private const MISSING_COUNT = 1;
    private const MISSING_HOURS = 1;
    private const MISSING_ACTIVITY_SUPPLEMENT = false;

    /**
     * @param array<array-key, mixed> $input
     */
    public function validate(DeclarationActivity $activity, #[SensitiveParameter] array $input): ValidationResult
    {
        return RawFields::validate($input, match ($activity) {
            DeclarationActivity::Childminder => $this->parseChildminder(...),
            DeclarationActivity::HomeCare => $this->parseHomeCare(...),
        });
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function fromInput(DeclarationActivity $activity, #[SensitiveParameter] array $input): EstimateRequest
    {
        return match ($activity) {
            DeclarationActivity::Childminder => $this->childminder($input),
            DeclarationActivity::HomeCare => $this->homeCare($input),
        };
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function childminder(#[SensitiveParameter] array $input): ChildminderEstimateRequest
    {
        return RawFields::resolve($input, $this->parseChildminder(...));
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function homeCare(#[SensitiveParameter] array $input): HomeCareEstimateRequest
    {
        return RawFields::resolve($input, $this->parseHomeCare(...));
    }

    private function parseChildminder(RawFields $fields): ?ChildminderEstimateRequest
    {
        $errorsBefore = $fields->errorCount();
        $employerPostalCode = $fields->optional('employer_postal_code', PostalCode::fromString(...));
        $employeePostalCode = $fields->optional('employee_postal_code', PostalCode::fromString(...));
        $netMonthlySalary = $fields->required('net_monthly_salary', Amount::fromDecimalString(...));
        $childrenCount = $fields->requiredInteger('children_count');
        $activityDays = $fields->requiredInteger('activity_days');
        $paidLeaveDays = $fields->optional('paid_leave_days', HalfDays::fromDecimalString(...));
        $majoredHours = $fields->integer('majored_hours');
        $complementaryHours = $fields->integer('complementary_hours');
        $request = $fields->build('', static fn(): ChildminderEstimateRequest => ChildminderEstimateRequest::of(
            $employerPostalCode,
            $employeePostalCode,
            $netMonthlySalary ?? Amount::fromCents(self::MISSING_SALARY_CENTS),
            $childrenCount ?? self::MISSING_COUNT,
            $activityDays ?? self::MISSING_COUNT,
            $paidLeaveDays,
            $majoredHours,
            $complementaryHours,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $request;
    }

    private function parseHomeCare(RawFields $fields): ?HomeCareEstimateRequest
    {
        $errorsBefore = $fields->errorCount();
        $employerPostalCode = $fields->optional('employer_postal_code', PostalCode::fromString(...));
        $employeePostalCode = $fields->optional('employee_postal_code', PostalCode::fromString(...));
        $netMonthlySalary = $fields->required('net_monthly_salary', Amount::fromDecimalString(...));
        $calculationMode = $fields->required('calculation_mode', CalculationMode::of(...));
        $hours = $fields->requiredInteger('hours');
        $overtimeHours25 = $fields->integer('overtime_hours_25');
        $overtimeHours50 = $fields->integer('overtime_hours_50');
        $youngestChildAge = $fields->required('youngest_child_age', YoungestChildAge::of(...));
        $activitySupplement = $fields->requiredBoolean('activity_supplement');
        $request = $fields->build('', static fn(): HomeCareEstimateRequest => HomeCareEstimateRequest::of(
            $employerPostalCode,
            $employeePostalCode,
            $netMonthlySalary ?? Amount::fromCents(self::MISSING_SALARY_CENTS),
            $calculationMode ?? CalculationMode::real(),
            $hours ?? self::MISSING_HOURS,
            $overtimeHours25,
            $overtimeHours50,
            $youngestChildAge ?? YoungestChildAge::underThree(),
            $activitySupplement ?? self::MISSING_ACTIVITY_SUPPLEMENT,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $request;
    }
}
