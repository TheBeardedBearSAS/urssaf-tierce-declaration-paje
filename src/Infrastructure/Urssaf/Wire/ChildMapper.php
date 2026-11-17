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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * `InputEnfant` (022 request and response, shared with the declarations, api-reference §2.3): `dtNaissanceEnfant`,
 * `nomEnfant`, `prenomEnfant`, in the key order of the official example.
 *
 * Decoding is strict (see {@see ChildCheckMapper} for the lenient use on responses): the three fields are required by the schema, so a child object that lacks one, carries a date
 * that is not a `Y-m-d` date (the placeholder `"string"` included, ADR 0001) or a name outside the contract is refused,
 * naming the field and never its value (ADR 0007). Whether the child object itself may be absent is decided by the
 * caller ({@see ChildCheckMapper}).
 */
final class ChildMapper
{
    /**
     * @return array{dtNaissanceEnfant: string, nomEnfant: string, prenomEnfant: string}
     */
    public static function encode(#[SensitiveParameter] Child $child): array
    {
        return [
            'dtNaissanceEnfant' => DateEncoding::encode($child->birthDate()),
            'nomEnfant' => $child->lastName()->value(),
            'prenomEnfant' => $child->firstName()->value(),
        ];
    }

    /**
     * @throws UnexpectedUrssafResponse when a field is missing, empty, not a string or invalid
     */
    public static function decode(Payload $payload): Child
    {
        return Child::of(
            DateEncoding::decode($payload->requireString('dtNaissanceEnfant'), 'dtNaissanceEnfant'),
            self::name($payload, 'nomEnfant', 'child_last_name'),
            self::name($payload, 'prenomEnfant', 'child_first_name'),
        );
    }

    private static function name(Payload $payload, string $field, string $subject): UpperCaseName
    {
        try {
            return UpperCaseName::of($payload->requireString($field), $subject);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy(\sprintf('field "%s" is invalid', $field), $exception);
        }
    }
}
