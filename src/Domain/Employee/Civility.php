<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;

/**
 * Civility code `cdCivilite` of an employee: one character (Swagger `minLength` = `maxLength` = 1). The PDF documents
 * `1` (M.) and `2` (Mme) only; any other single character is accepted locally and reported by
 * {@see self::isDocumented()} (ADR 0009).
 */
final readonly class Civility
{
    private const SUBJECT = 'civility';
    private const MISTER = '1';
    private const MADAM = '2';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "civility.too_short" or "civility.too_long"
     */
    public static function of(string $value): self
    {
        StringConstraint::length($value, self::SUBJECT, 1, 1);

        return new self($value);
    }

    public static function mister(): self
    {
        return new self(self::MISTER);
    }

    public static function madam(): self
    {
        return new self(self::MADAM);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDocumented(): bool
    {
        return $this->value === self::MISTER || $this->value === self::MADAM;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
