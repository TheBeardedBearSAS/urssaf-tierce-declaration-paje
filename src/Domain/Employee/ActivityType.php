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
 * Activity type `typeActiviteSalarie` of an employee to create (023): one character (Swagger `minLength` =
 * `maxLength` = 1). The PDF documents `A` (childminder) and `G` (home care) only; any other single character is
 * accepted locally and reported by {@see self::isDocumented()} (ADR 0009).
 */
final readonly class ActivityType
{
    private const SUBJECT = 'activity_type';
    private const CHILDMINDER = 'A';
    private const HOME_CARE = 'G';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "activity_type.too_short" or "activity_type.too_long"
     */
    public static function of(string $value): self
    {
        StringConstraint::length($value, self::SUBJECT, 1, 1);

        return new self($value);
    }

    public static function childminder(): self
    {
        return new self(self::CHILDMINDER);
    }

    public static function homeCare(): self
    {
        return new self(self::HOME_CARE);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDocumented(): bool
    {
        return $this->value === self::CHILDMINDER || $this->value === self::HOME_CARE;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
