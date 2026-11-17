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

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;

/**
 * `InputFinDeContratAma` (api-reference §2.7). The deprecated `enfants` is emitted only when the list is not empty,
 * each child shaped like the top-level ones ({@see ChildminderChildMapper}, api-reference §5). An object without any
 * member is still a JSON object (`{}`): a contract end was given, with no indemnity.
 */
final class ChildminderContractEndMapper
{
    public static function encode(#[SensitiveParameter] ChildminderContractEnd $contractEnd): JsonObject
    {
        $children = $contractEnd->children();

        return JsonObject::of(OptionalWireValues::present([
            'mntIndDepartRetraite' => OptionalWireValues::amount($contractEnd->retirementIndemnity()),
            'mntIndCcp' => OptionalWireValues::amount($contractEnd->paidLeaveCompensation()),
            'mntIndPreavis' => OptionalWireValues::amount($contractEnd->noticeIndemnity()),
            'mntIndPrecarite' => OptionalWireValues::amount($contractEnd->precarityIndemnity()),
            'mntIndRupture' => OptionalWireValues::amount($contractEnd->terminationIndemnity()),
            'enfants' => $children === [] ? null : array_map(ChildminderChildMapper::encode(...), $children),
        ]));
    }
}
