<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;

/**
 * Calculation mode of a home childcare salary (`cdModeCalcul`, api-reference §3.2). The Swagger only fixes a length of
 * 1; the PDF documents `R`, `F` and `S`. Any one-character value is accepted (ADR 0009) and
 * {@see self::isDocumented()} tells whether the PDF lists it.
 */
final readonly class CalculationMode
{
    private const SUBJECT = 'calculation_mode';
    private const LENGTH = 1;
    private const REAL = 'R';
    private const FLAT_RATE = 'F';
    private const SPECIFIC = 'S';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "calculation_mode.too_short" or "calculation_mode.too_long"
     */
    public static function of(string $value): self
    {
        StringConstraint::length($value, self::SUBJECT, self::LENGTH, self::LENGTH);

        return new self($value);
    }

    /**
     * Real mode (`R`), the default and the only one outside the overseas departments.
     */
    public static function real(): self
    {
        return new self(self::REAL);
    }

    /**
     * Flat-rate mode (`F`).
     */
    public static function flatRate(): self
    {
        return new self(self::FLAT_RATE);
    }

    /**
     * Specific mode (`S`), overseas home childcare only.
     */
    public static function specific(): self
    {
        return new self(self::SPECIFIC);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDocumented(): bool
    {
        return \in_array($this->value, [self::REAL, self::FLAT_RATE, self::SPECIFIC], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
