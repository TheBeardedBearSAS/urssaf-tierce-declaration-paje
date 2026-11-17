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

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;

/**
 * NIR `nirSalarie`: exactly 15 characters (Swagger `minLength` = `maxLength` = 15). No checksum nor pattern is
 * published, none is invented.
 * Personal data: masked in debug output, no string conversion (ADR 0007).
 */
final readonly class Nir
{
    public const LENGTH = 15;
    private const SUBJECT = 'nir';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "nir.too_short" or "nir.too_long"
     */
    public static function of(#[SensitiveParameter] string $value): self
    {
        StringConstraint::length($value, self::SUBJECT, self::LENGTH, self::LENGTH);

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
