<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;

/**
 * Age class of the youngest child to look after (`indcEnfantPlusJeuneAgarder`, api-reference §3.2). The Swagger only
 * fixes a length of 1; the PDF documents `1`, `2` and `3`. Any one-character value is accepted (ADR 0009) and
 * {@see self::isDocumented()} tells whether the PDF lists it.
 */
final readonly class YoungestChildAge
{
    private const SUBJECT = 'youngest_child_age';
    private const LENGTH = 1;
    private const UNDER_THREE = '1';
    private const THREE_TO_SIX = '2';
    private const OVER_SIX = '3';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "youngest_child_age.too_short" or "youngest_child_age.too_long"
     */
    public static function of(string $value): self
    {
        StringConstraint::length($value, self::SUBJECT, self::LENGTH, self::LENGTH);

        return new self($value);
    }

    /**
     * `1`: child of 0 to 3 years without activity supplement.
     */
    public static function underThree(): self
    {
        return new self(self::UNDER_THREE);
    }

    /**
     * `2`: child of 3 to 6 years.
     */
    public static function threeToSix(): self
    {
        return new self(self::THREE_TO_SIX);
    }

    /**
     * `3`: child over 6 years.
     */
    public static function overSix(): self
    {
        return new self(self::OVER_SIX);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDocumented(): bool
    {
        return \in_array($this->value, [self::UNDER_THREE, self::THREE_TO_SIX, self::OVER_SIX], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
