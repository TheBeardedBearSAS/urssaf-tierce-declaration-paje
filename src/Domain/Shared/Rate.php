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
 * Exact decimal rate such as the withholding tax rate (`txPas`, ADR 0008): kept as text in canonical form, with no
 * precision cap and no bound (the response carries none), never a float.
 *
 * Canonical form: no leading zero (except "0" itself), no trailing zero after the point, no point without decimals,
 * "0" for every zero ("007.500" becomes "7.5", "-0.0" becomes "0").
 *
 * Exponent notation is accepted and expanded exactly, without any float: PHP renders the shortest round-trip form of a
 * small or large float that way (`0.00001` is "1.0E-5"), and ADR 0008 builds `txPas` from that form ("1.0E-5"
 * becomes "0.00001", "1.5e2" becomes "150"). The exponent holds at most 3 digits.
 */
final readonly class Rate
{
    private const SUBJECT = 'rate';
    private const PATTERN = '/^(-?)(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d{1,3}))?$/D';
    private const ZERO = '0';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "rate.invalid_format"
     */
    public static function fromString(string $value): self
    {
        $matches = [];
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'decimal number with "." as separator');
        }
        $integer = $matches[2];
        $fraction = $matches[3] ?? '';
        $exponent = $matches[4] ?? null;
        if ($exponent !== null) {
            [$integer, $fraction] = self::shift($integer, $fraction, (int) $exponent);
        }
        $integer = ltrim($integer, self::ZERO);
        $integer = $integer === '' ? self::ZERO : $integer;
        $fraction = rtrim($fraction, self::ZERO);
        $isZero = $integer === self::ZERO && $fraction === '';
        $sign = $matches[1] === '-' && !$isZero ? '-' : '';
        $decimals = $fraction === '' ? '' : '.' . $fraction;

        return new self($sign . $integer . $decimals);
    }

    /**
     * Moves the decimal point by `$exponent` places: the digits stay, only the split between integer and fraction moves.
     *
     * @return array{string, string} integer part, fraction
     */
    private static function shift(string $integer, string $fraction, int $exponent): array
    {
        $digits = $integer . $fraction;
        $point = \strlen($integer) + $exponent;
        // str_pad() leaves the text untouched when the target length is not larger: the point moving before the first
        // digit pads on the left, the point moving past the last one pads on the right, anything else pads nothing.
        $leftPadded = str_pad($digits, \strlen($digits) - $point, self::ZERO, STR_PAD_LEFT);
        $split = $point + \strlen($leftPadded) - \strlen($digits);
        $padded = str_pad($leftPadded, $split, self::ZERO, STR_PAD_RIGHT);

        return [substr($padded, 0, $split), substr($padded, $split)];
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
