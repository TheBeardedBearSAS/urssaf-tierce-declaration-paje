<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;

/**
 * A contribution estimate request (ADR 0004 sum type): {@see ChildminderEstimateRequest} (`InputEstimationAma`) or
 * {@see HomeCareEstimateRequest} (`InputEstimationGed`). Both share `InputEstimation`.
 *
 * A missing postal code means "not sent": the Swagger default `75000` is applied by URSSAF, never injected locally.
 */
interface EstimateRequest
{
    /**
     * `cdPostalEmployeur`.
     */
    public function employerPostalCode(): ?PostalCode;

    /**
     * `cdPostalSalarie`.
     */
    public function employeePostalCode(): ?PostalCode;

    /**
     * `mntSalaireNetMensuel`.
     */
    public function netMonthlySalary(): Amount;
}
