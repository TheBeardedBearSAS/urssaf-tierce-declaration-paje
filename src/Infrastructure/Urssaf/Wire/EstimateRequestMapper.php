<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;

/**
 * Body of operations 040 (`InputEstimationAma`) and 041 (`InputEstimationGed`), api-reference §2.5 and §2.6: no query
 * and no employer context. A postal code that is not set is omitted: the Swagger default `75000` is applied by URSSAF,
 * never injected here. The key order is the one of the official examples (alphabetical).
 */
final class EstimateRequestMapper
{
    /**
     * @return array<string, mixed>
     *
     * @throws InvariantViolation "estimate_request.unsupported" for a request that is neither AMA nor GED
     */
    public static function body(EstimateRequest $request): array
    {
        return match (true) {
            $request instanceof ChildminderEstimateRequest => self::childminder($request),
            $request instanceof HomeCareEstimateRequest => self::homeCare($request),
            default => throw InvariantViolation::because(
                'estimate_request.unsupported',
                'The estimate request is neither a childminder nor a home childcare request.',
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function childminder(ChildminderEstimateRequest $request): array
    {
        return OptionalWireValues::present([
            'cdPostalEmployeur' => self::postalCode($request->employerPostalCode()),
            'cdPostalSalarie' => self::postalCode($request->employeePostalCode()),
            'mntSalaireNetMensuel' => AmountEncoding::encode($request->netMonthlySalary()),
            'nbEnfantsGardes' => $request->childrenCount(),
            'nbHeuresComplementaires' => $request->complementaryHours(),
            'nbHeuresMajorees' => $request->majoredHours(),
            'nbJoursActivite' => $request->activityDays(),
            'nbJoursCongesPayes' => OptionalWireValues::halfDays($request->paidLeaveDays()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeCare(HomeCareEstimateRequest $request): array
    {
        return OptionalWireValues::present([
            'cdModeCalcul' => $request->calculationMode()->value(),
            'cdPostalEmployeur' => self::postalCode($request->employerPostalCode()),
            'cdPostalSalarie' => self::postalCode($request->employeePostalCode()),
            'complementActiviteOk' => $request->hasActivitySupplement(),
            'indcEnfantPlusJeuneAgarder' => $request->youngestChildAge()->value(),
            'mntSalaireNetMensuel' => AmountEncoding::encode($request->netMonthlySalary()),
            'nbHeures' => $request->hours(),
            'nbHrSupMajA25' => $request->overtimeHours25(),
            'nbHrSupMajA50' => $request->overtimeHours50(),
        ]);
    }

    private static function postalCode(?PostalCode $postalCode): ?string
    {
        return $postalCode?->value();
    }
}
