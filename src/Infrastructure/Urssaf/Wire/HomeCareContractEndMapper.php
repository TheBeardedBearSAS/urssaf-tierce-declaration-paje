<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;

/**
 * `InputFinDeContratGed` (api-reference §2.8). The dismissal indemnity is `mntIndLicenciement`, the only name the
 * Swagger knows (the PDF examples send `mntIndRupture`, api-reference §5). An object without any member is still a JSON
 * object (`{}`).
 */
final class HomeCareContractEndMapper
{
    public static function encode(HomeCareContractEnd $contractEnd): JsonObject
    {
        return JsonObject::of(OptionalWireValues::present([
            'mntIndDepartRetraite' => OptionalWireValues::amount($contractEnd->retirementIndemnity()),
            'mntIndCcp' => OptionalWireValues::amount($contractEnd->paidLeaveCompensation()),
            'mntIndPreavis' => OptionalWireValues::amount($contractEnd->noticeIndemnity()),
            'mntIndPrecarite' => OptionalWireValues::amount($contractEnd->precarityIndemnity()),
            'mntIndLicenciement' => OptionalWireValues::amount($contractEnd->dismissalIndemnity()),
        ]));
    }
}
