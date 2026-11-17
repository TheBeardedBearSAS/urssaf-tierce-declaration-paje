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
 * Swagger string constraints (`minLength`, `maxLength`, `pattern`) shared by the employer, employee and address
 * value objects. Violation codes are "<subject>.too_short", "<subject>.too_long" and "<subject>.invalid_format"; the
 * rejected value is never part of the exception.
 *
 * @internal
 */
final class StringConstraint
{
    /**
     * @throws InvalidValue "<subject>.too_short" or "<subject>.too_long"
     */
    public static function length(string $value, string $subject, int $min, int $max): void
    {
        $length = mb_strlen($value);
        if ($length < $min) {
            throw InvalidValue::tooShort($subject, $min);
        }
        if ($length > $max) {
            throw InvalidValue::tooLong($subject, $max);
        }
    }

    /**
     * @throws InvalidValue "<subject>.too_long"
     */
    public static function atMost(string $value, string $subject, int $max): void
    {
        if (mb_strlen($value) > $max) {
            throw InvalidValue::tooLong($subject, $max);
        }
    }

    /**
     * @param non-empty-string $pattern anchored PCRE pattern
     *
     * @throws InvalidValue "<subject>.invalid_format"
     */
    public static function matches(string $value, string $subject, string $pattern, string $expectedFormat): void
    {
        if (preg_match($pattern, $value) !== 1) {
            throw InvalidValue::invalidFormat($subject, $expectedFormat);
        }
    }
}
