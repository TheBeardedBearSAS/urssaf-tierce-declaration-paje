<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\AmountRules;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Contribution estimate request for a childminder (`InputEstimationAma`, api-reference §2.5), with the Swagger bounds.
 * The net monthly salary has a maximum of 9999.99 and no minimum here (the "> 0" rule is on `InputDeclCommun` only).
 *
 * Violation paths: "netMonthlySalary", "childrenCount", "activityDays", "paidLeaveDays", "majoredHours",
 * "complementaryHours".
 */
final readonly class ChildminderEstimateRequest implements EstimateRequest
{
    private const MIN_CHILDREN = 1;
    private const MAX_CHILDREN = 5;
    private const MIN_ACTIVITY_DAYS = 1;
    private const MAX_ACTIVITY_DAYS = 31;
    private const MAX_PAID_LEAVE_HALF_DAYS = 198;
    private const MAX_EXTRA_HOURS = 999;

    private function __construct(
        private ?PostalCode $employerPostalCode,
        private ?PostalCode $employeePostalCode,
        private Amount $netMonthlySalary,
        private int $childrenCount,
        private int $activityDays,
        private ?HalfDays $paidLeaveDays,
        private ?int $majoredHours,
        private ?int $complementaryHours,
    ) {}

    /**
     * @param int           $childrenCount      `nbEnfantsGardes`, 1 to 5
     * @param int           $activityDays       `nbJoursActivite`, 1 to 31
     * @param HalfDays|null $paidLeaveDays      `nbJoursCongesPayes`, at most 99
     * @param int|null      $majoredHours       `nbHeuresMajorees`, 0 to 999
     * @param int|null      $complementaryHours `nbHeuresComplementaires`, 0 to 999
     *
     * @throws ValidationFailed with every violation
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        ?PostalCode $employerPostalCode,
        ?PostalCode $employeePostalCode,
        Amount $netMonthlySalary,
        int $childrenCount,
        int $activityDays,
        ?HalfDays $paidLeaveDays,
        ?int $majoredHours,
        ?int $complementaryHours,
    ): self {
        $collector = new ViolationCollector();
        AmountRules::capped($collector, 'netMonthlySalary', 'net_monthly_salary', $netMonthlySalary);
        $collector->optionalInteger('childrenCount', 'children_count', $childrenCount, self::MIN_CHILDREN, self::MAX_CHILDREN);
        $collector->optionalInteger(
            'activityDays',
            'activity_days',
            $activityDays,
            self::MIN_ACTIVITY_DAYS,
            self::MAX_ACTIVITY_DAYS,
        );
        if ($paidLeaveDays instanceof HalfDays) {
            $collector->attempt('paidLeaveDays', static fn(): HalfDays => $paidLeaveDays->assertAtMost(
                HalfDays::fromHalfDays(self::MAX_PAID_LEAVE_HALF_DAYS),
                'paid_leave_days',
            ));
        }
        $collector->optionalInteger('majoredHours', 'majored_hours', $majoredHours, 0, self::MAX_EXTRA_HOURS);
        $collector->optionalInteger(
            'complementaryHours',
            'complementary_hours',
            $complementaryHours,
            0,
            self::MAX_EXTRA_HOURS,
        );
        $collector->result()->throwIfInvalid();

        return new self(
            $employerPostalCode,
            $employeePostalCode,
            $netMonthlySalary,
            $childrenCount,
            $activityDays,
            $paidLeaveDays,
            $majoredHours,
            $complementaryHours,
        );
    }

    #[Override]
    public function employerPostalCode(): ?PostalCode
    {
        return $this->employerPostalCode;
    }

    #[Override]
    public function employeePostalCode(): ?PostalCode
    {
        return $this->employeePostalCode;
    }

    #[Override]
    public function netMonthlySalary(): Amount
    {
        return $this->netMonthlySalary;
    }

    /**
     * `nbEnfantsGardes`.
     */
    public function childrenCount(): int
    {
        return $this->childrenCount;
    }

    /**
     * `nbJoursActivite`.
     */
    public function activityDays(): int
    {
        return $this->activityDays;
    }

    /**
     * `nbJoursCongesPayes`.
     */
    public function paidLeaveDays(): ?HalfDays
    {
        return $this->paidLeaveDays;
    }

    /**
     * `nbHeuresMajorees`.
     */
    public function majoredHours(): ?int
    {
        return $this->majoredHours;
    }

    /**
     * `nbHeuresComplementaires`.
     */
    public function complementaryHours(): ?int
    {
        return $this->complementaryHours;
    }
}
