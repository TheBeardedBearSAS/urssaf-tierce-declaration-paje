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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * `OutputPreDeclaration` of 045 / 046 (api-reference §2.7). Every field is optional in the Swagger:
 * - `declaration`: absent is null, otherwise {@see DeclarationAmountsMapper};
 * - `indcDeclPajemploiPlus`: absent is `false` (the Swagger default);
 * - `dateVirement`: absent is null, otherwise a strict `Y-m-d` date (the placeholder "string" is refused, ADR 0001);
 * - `verificationEnfantPe`: absent is an empty list; an entry without `reponseStatutEnfant` is skipped (api-reference
 *   §5), the others are decoded by {@see ChildCheckMapper::decode()};
 * - `erreursNonBloquantes`: absent is an empty list, they never fail the call (ADR 0008).
 * A malformed value is refused, naming the field and never its value.
 */
final class PreDeclarationResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse when the body is not a JSON object (`{}` is accepted) or a field is malformed
     */
    public static function map(Payload $payload): PreDeclarationResult
    {
        $payload->requireJsonObject();

        return PreDeclarationResult::of(
            self::amounts($payload),
            $payload->optionalBool('indcDeclPajemploiPlus') ?? false,
            self::transferDate($payload),
            self::childChecks($payload),
            NonBlockingMessagesMapper::map($payload),
        );
    }

    private static function amounts(Payload $payload): ?DeclarationAmounts
    {
        $declaration = $payload->object('declaration');

        return $declaration instanceof Payload ? DeclarationAmountsMapper::map($declaration) : null;
    }

    private static function transferDate(Payload $payload): ?CalendarDate
    {
        $value = $payload->optionalString('dateVirement');

        return $value === null ? null : DateEncoding::decode($value, 'dateVirement');
    }

    /**
     * @return list<ChildCheck>
     */
    private static function childChecks(Payload $payload): array
    {
        $checks = [];
        foreach ($payload->items('verificationEnfantPe') as $entry) {
            $check = ChildCheckMapper::decode($entry);

            if ($check instanceof ChildCheck) {
                $checks[] = $check;
            }
        }

        return $checks;
    }
}
