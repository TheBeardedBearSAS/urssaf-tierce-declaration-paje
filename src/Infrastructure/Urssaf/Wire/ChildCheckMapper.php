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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlementStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * `VerificationEnfantPe` (022 response, api-reference §2.3, §5): `inputEnfantPe` and `reponseStatutEnfant`.
 *
 * Both fields are optional in the Swagger: an item without `reponseStatutEnfant` carries no answer and is skipped
 * (null); an absent `inputEnfantPe` gives a check without child. A present `inputEnfantPe` is decoded by
 * {@see ChildMapper::decode()}, but leniently: a child that cannot be read (wrong type, empty or too long name,
 * other spelling, invalid date) becomes a check without child, because a recorded declaration must never become an
 * unknown outcome for an informational fragment. A status that is not an integer is refused; an undocumented integer
 * is kept.
 */
final class ChildCheckMapper
{
    /**
     * @throws UnexpectedUrssafResponse when the status is not an integer
     */
    public static function decode(Payload $payload): ?ChildCheck
    {
        $status = $payload->int('reponseStatutEnfant');

        if ($status === null) {
            return null;
        }

        return ChildCheck::of(self::echoedChild($payload), ChildEntitlementStatus::of($status));
    }

    /**
     * The child echoed by URSSAF is informational: a fragment that cannot be read with the request-side rules
     * (spelling, length, date) is treated as no child, never as a failed response (ADR 0008, api-reference §5).
     */
    private static function echoedChild(Payload $payload): ?Child
    {
        try {
            $child = $payload->object('inputEnfantPe');

            return $child instanceof Payload ? ChildMapper::decode($child) : null;
        } catch (UnexpectedUrssafResponse) {
            return null;
        }
    }
}
