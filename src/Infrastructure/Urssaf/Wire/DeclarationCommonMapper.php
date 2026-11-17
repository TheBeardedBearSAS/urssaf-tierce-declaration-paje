<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;

/**
 * `InputDeclCommun` (api-reference §2.7), shared by the four declaration operations. The key order is the one of the
 * official examples. Optional values that are not set are omitted.
 */
final class DeclarationCommonMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function encode(DeclarationCommon $common): array
    {
        $period = $common->period();

        return OptionalWireValues::present([
            'dtDebutPeriode' => DateEncoding::encode($period->start()),
            'dtFinPeriode' => DateEncoding::encode($period->end()),
            'dtPaiementSalaire' => DateEncoding::encode($period->paymentDate()),
            'mntAcompteSalarie' => OptionalWireValues::amount($common->employeeAdvance()),
            'mntIndemnitesKilometriques' => OptionalWireValues::amount($common->mileageAllowance()),
            'mntSalaireHoraireNet' => OptionalWireValues::amount($common->netHourlyRate()),
            'mntSalaireNetMensuel' => AmountEncoding::encode($common->netMonthlySalary()),
            'nbHeures' => $common->hours(),
            'nbHeuresSpecifiques' => $common->specificHours(),
            'nbJoursCongesPayes' => OptionalWireValues::halfDays($common->paidLeaveDays()),
        ]);
    }
}
