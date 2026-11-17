<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\AmountRules;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Contribution estimate request for home childcare (`InputEstimationGed`, api-reference §2.6), with the Swagger
 * bounds. The net monthly salary has a maximum of 9999.99 and no minimum here.
 *
 * Violation paths: "netMonthlySalary", "hours", "overtimeHours25", "overtimeHours50".
 */
final readonly class HomeCareEstimateRequest implements EstimateRequest
{
    private const MIN_HOURS = 1;
    private const MAX_HOURS = 1000;
    private const MAX_OVERTIME_HOURS_25 = 35;
    private const MAX_OVERTIME_HOURS_50 = 40;

    private function __construct(
        private ?PostalCode $employerPostalCode,
        private ?PostalCode $employeePostalCode,
        private Amount $netMonthlySalary,
        private CalculationMode $calculationMode,
        private int $hours,
        private ?int $overtimeHours25,
        private ?int $overtimeHours50,
        private YoungestChildAge $youngestChildAge,
        private bool $activitySupplement,
    ) {}

    /**
     * @param CalculationMode  $calculationMode    `cdModeCalcul`
     * @param int              $hours              `nbHeures`, 1 to 1000
     * @param int|null         $overtimeHours25    `nbHrSupMajA25`, 0 to 35
     * @param int|null         $overtimeHours50    `nbHrSupMajA50`, 0 to 40
     * @param YoungestChildAge $youngestChildAge   `indcEnfantPlusJeuneAgarder`
     * @param bool             $activitySupplement `complementActiviteOk`: the employer receives an activity supplement
     *
     * @throws ValidationFailed with every violation
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        ?PostalCode $employerPostalCode,
        ?PostalCode $employeePostalCode,
        Amount $netMonthlySalary,
        CalculationMode $calculationMode,
        int $hours,
        ?int $overtimeHours25,
        ?int $overtimeHours50,
        YoungestChildAge $youngestChildAge,
        bool $activitySupplement,
    ): self {
        $collector = new ViolationCollector();
        AmountRules::capped($collector, 'netMonthlySalary', 'net_monthly_salary', $netMonthlySalary);
        $collector->optionalInteger('hours', 'hours', $hours, self::MIN_HOURS, self::MAX_HOURS);
        $collector->optionalInteger('overtimeHours25', 'overtime_hours_25', $overtimeHours25, 0, self::MAX_OVERTIME_HOURS_25);
        $collector->optionalInteger('overtimeHours50', 'overtime_hours_50', $overtimeHours50, 0, self::MAX_OVERTIME_HOURS_50);
        $collector->result()->throwIfInvalid();

        return new self(
            $employerPostalCode,
            $employeePostalCode,
            $netMonthlySalary,
            $calculationMode,
            $hours,
            $overtimeHours25,
            $overtimeHours50,
            $youngestChildAge,
            $activitySupplement,
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
     * `cdModeCalcul`.
     */
    public function calculationMode(): CalculationMode
    {
        return $this->calculationMode;
    }

    /**
     * `nbHeures`.
     */
    public function hours(): int
    {
        return $this->hours;
    }

    /**
     * `nbHrSupMajA25`.
     */
    public function overtimeHours25(): ?int
    {
        return $this->overtimeHours25;
    }

    /**
     * `nbHrSupMajA50`.
     */
    public function overtimeHours50(): ?int
    {
        return $this->overtimeHours50;
    }

    /**
     * `indcEnfantPlusJeuneAgarder`.
     */
    public function youngestChildAge(): YoungestChildAge
    {
        return $this->youngestChildAge;
    }

    /**
     * `complementActiviteOk`.
     */
    public function hasActivitySupplement(): bool
    {
        return $this->activitySupplement;
    }
}
