<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception;

/**
 * Thrown when a value object cannot be built from the given input.
 *
 * The "subject" is the snake_case name of the value (e.g. "tenant_id", "amount"); the violation code is
 * "<subject>.<reason>" (e.g. "tenant_id.too_long").
 *
 * One named constructor per kind of violation, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class InvalidValue extends DomainException
{
    /**
     * @param array<string, scalar> $params
     */
    public static function because(string $violationCode, string $message, array $params = []): self
    {
        return new self($message, $violationCode, $params);
    }

    public static function empty(string $subject): self
    {
        return new self(\sprintf('Invalid %s: the value must not be empty.', $subject), $subject . '.empty', []);
    }

    public static function tooLong(string $subject, int $maxLength): self
    {
        return new self(
            \sprintf('Invalid %s: the value must not exceed %d characters.', $subject, $maxLength),
            $subject . '.too_long',
            ['max_length' => $maxLength],
        );
    }

    public static function tooShort(string $subject, int $minLength): self
    {
        return new self(
            \sprintf('Invalid %s: the value must have at least %d characters.', $subject, $minLength),
            $subject . '.too_short',
            ['min_length' => $minLength],
        );
    }

    public static function invalidFormat(string $subject, string $expectedFormat): self
    {
        return new self(
            \sprintf('Invalid %s: the value does not match the expected format (%s).', $subject, $expectedFormat),
            $subject . '.invalid_format',
            ['expected_format' => $expectedFormat],
        );
    }

    public static function negative(string $subject): self
    {
        return new self(\sprintf('Invalid %s: the value must not be negative.', $subject), $subject . '.negative', []);
    }

    public static function notPositive(string $subject): self
    {
        return new self(
            \sprintf('Invalid %s: the value must be strictly positive.', $subject),
            $subject . '.not_positive',
            [],
        );
    }

    public static function tooManyDecimals(string $subject, int $maxDecimals): self
    {
        return new self(
            \sprintf('Invalid %s: the value must not have more than %d decimals.', $subject, $maxDecimals),
            $subject . '.too_many_decimals',
            ['max_decimals' => $maxDecimals],
        );
    }

    public static function tooLarge(string $subject): self
    {
        return new self(
            \sprintf('Invalid %s: the value exceeds the supported range.', $subject),
            $subject . '.too_large',
            [],
        );
    }

    /**
     * @param int|string $min lower bound as given (an integer for counts, a decimal string such as "0.00" for amounts)
     * @param int|string $max upper bound, same convention
     */
    public static function outOfRange(string $subject, int|string $min, int|string $max): self
    {
        return new self(
            \sprintf('Invalid %s: the value must be between %s and %s.', $subject, $min, $max),
            $subject . '.out_of_range',
            ['min' => $min, 'max' => $max],
        );
    }

    public static function unknown(string $subject): self
    {
        return new self(
            \sprintf('Invalid %s: the value is not one of the accepted codes.', $subject),
            $subject . '.unknown',
            [],
        );
    }

    public static function invalidCharacters(string $subject): self
    {
        return new self(
            \sprintf('Invalid %s: the value contains characters that are not allowed.', $subject),
            $subject . '.invalid_characters',
            [],
        );
    }

    public static function notMultipleOf(string $subject, string $step): self
    {
        return new self(
            \sprintf('Invalid %s: the value must be a multiple of %s.', $subject, $step),
            $subject . '.not_multiple_of',
            ['step' => $step],
        );
    }
}
