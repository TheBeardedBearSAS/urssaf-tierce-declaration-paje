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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Rates (`txPas`) are read from the shortest round-trip representation of the decoded JSON number (ADR 0008): PHP
 * `serialize_precision = -1`, as used by `var_export()`, so 7.5 is "7.5" and 0.00001 is "1.0E-5" (expanded exactly by
 * {@see Rate::fromString()}). An integer beyond the integer range arrives as its digit string
 * (`JSON_BIGINT_AS_STRING`, see {@see Payload::numeric()}) and is taken as is. No precision cap, no bound, never a float in the domain.
 */
final class RateEncoding
{
    /**
     * @throws UnexpectedUrssafResponse when the value is not a finite decimal number (`INF` and `NAN` are not decimal literals; names the field only)
     */
    public static function decode(int|float|string $value, string $field): Rate
    {
        return self::fromLiteral(\is_string($value) ? $value : var_export($value, true), $field);
    }

    /**
     * @throws UnexpectedUrssafResponse when the literal is not a decimal number (names the field only)
     */
    public static function fromLiteral(string $literal, string $field): Rate
    {
        try {
            return Rate::fromString($literal);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy(\sprintf('field "%s" is not a valid rate', $field), $exception);
        }
    }

    /**
     * Same as {@see self::decode()}; an absent value stays absent.
     *
     * @throws UnexpectedUrssafResponse
     */
    public static function decodeOptional(int|float|string|null $value, string $field): ?Rate
    {
        return $value === null ? null : self::decode($value, $field);
    }
}
