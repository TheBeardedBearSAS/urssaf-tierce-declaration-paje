<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Strict conversions between decimal strings and scaled integers.
 *
 * @internal shared by Amount and HalfDays
 */
final class Decimal
{
    private const ZERO = '0';
    private const NEGATIVE_SIGN = '-';
    private const START = 0;
    private const PATTERN = '/^(-?)(0|[1-9]\d*)(?:\.(\d+))?$/D';

    /**
     * Parses "12.5" with 2 decimals into 1250. Accepted syntax: digits, optional "." followed by at least one digit,
     * no sign, no leading zero, no exponent, no thousands separator, no surrounding whitespace.
     *
     * @throws InvalidValue
     */
    public static function parse(string $value, string $subject, int $decimals, int $maxIntegerDigits): int
    {
        $scaled = self::parseSigned($value, $subject, $decimals, $maxIntegerDigits);
        if (str_starts_with($value, self::NEGATIVE_SIGN)) {
            throw InvalidValue::negative($subject);
        }

        return $scaled;
    }

    /**
     * Same syntax as {@see self::parse()} with an optional leading "-" ("-0.5" with 2 decimals gives -50, "-0" gives 0).
     *
     * @throws InvalidValue
     */
    public static function parseSigned(string $value, string $subject, int $decimals, int $maxIntegerDigits): int
    {
        $matches = [];
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw InvalidValue::invalidFormat($subject, 'decimal number with "." as separator');
        }
        $fraction = $matches[3] ?? '';
        if (\strlen($fraction) > $decimals) {
            throw InvalidValue::tooManyDecimals($subject, $decimals);
        }
        if (\strlen($matches[2]) > $maxIntegerDigits) {
            throw InvalidValue::tooLarge($subject);
        }

        return (int) ($matches[1] . $matches[2] . str_pad($fraction, $decimals, self::ZERO));
    }

    /**
     * Formats 1250 (scale 2) as "12.50"; with $minDecimals lower than $decimals trailing zeros are trimmed down to
     * $minDecimals digits (1750, scale 3, min 0 => "1.75"). A negative value keeps its sign ("-0.50").
     */
    public static function format(int $scaled, int $decimals, int $minDecimals): string
    {
        $negative = $scaled < 0;
        $absolute = $negative ? substr((string) $scaled, 1) : (string) $scaled; // string: PHP_INT_MIN has no int absolute
        $digits = str_pad($absolute, $decimals + 1, self::ZERO, STR_PAD_LEFT);
        $integer = substr($digits, self::START, -$decimals);
        $fraction = substr($digits, -$decimals);
        $trimmed = str_pad(rtrim($fraction, self::ZERO), $minDecimals, self::ZERO);
        $formatted = $trimmed === '' ? $integer : $integer . '.' . $trimmed;

        return $negative ? self::NEGATIVE_SIGN . $formatted : $formatted;
    }
}
