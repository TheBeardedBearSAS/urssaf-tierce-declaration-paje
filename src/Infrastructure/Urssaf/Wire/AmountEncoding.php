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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Amounts are JSON numbers in euros with 2 decimals (ADR 0008). Requests emit exactly 2 decimals; responses are read
 * from the decoded number rounded to the cent. No sign and no bound is checked on a response: a declaration recorded
 * by URSSAF never becomes an unknown outcome because of a computed amount.
 */
final class AmountEncoding
{
    private const DECIMALS = 2;

    /**
     * "12.50", never a float.
     */
    public static function encode(Amount $amount): JsonDecimal
    {
        return JsonDecimal::fromString($amount->toDecimalString());
    }

    /**
     * A float is rendered with `round($v, 2)` then `%.2F` (the shortest round-trip representation of a 2-decimal float
     * is exact); an integer is taken as is; a string is a digit string standing for an integer beyond the platform
     * range (`JSON_BIGINT_AS_STRING`).
     *
     * @throws UnexpectedUrssafResponse when the value is not finite or does not fit an amount (names the field only)
     */
    public static function decode(int|float|string $value, string $field): Amount
    {
        if (\is_float($value) && !is_finite($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(self::reason($field));
        }

        $decimal = \is_float($value) ? \sprintf('%.2F', round($value, self::DECIMALS)) : (string) $value;

        try {
            return Amount::fromDecimalString($decimal);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy(self::reason($field), $exception);
        }
    }

    /**
     * Same as {@see self::decode()}; an absent value stays absent.
     *
     * @throws UnexpectedUrssafResponse
     */
    public static function decodeOptional(int|float|string|null $value, string $field): ?Amount
    {
        return $value === null ? null : self::decode($value, $field);
    }

    private static function reason(string $field): string
    {
        return \sprintf('field "%s" is not a valid amount', $field);
    }
}
