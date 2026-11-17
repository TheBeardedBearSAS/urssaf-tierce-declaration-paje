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
 * Street type `typeVoie`: one to three characters matching the Swagger pattern `[A-Z'\-\s]` (anchored here, the Swagger
 * pattern is unanchored and could never fail). The PDF lists 33 codes (§3.4); any other value of the pattern is accepted
 * and reported by {@see self::isDocumented()} (ADR 0009). Part of an employee address.
 */
final readonly class StreetType
{
    private const SUBJECT = 'street_type';
    private const MAX_LENGTH = 3;
    private const PATTERN = '/^[A-Z\'\-\s]+$/D';
    private const DOCUMENTED = [
        'ALL',
        'AV',
        'BAT',
        'BD',
        'CAN',
        'CHS',
        'CHE',
        'CI',
        'CL',
        'C',
        'COG',
        'FG',
        'HAM',
        'IMB',
        'IMP',
        'LOT',
        'MAI',
        'MTE',
        'PAS',
        'PAR',
        'PAV',
        'PL',
        'POR',
        'Q',
        'QU',
        'RAM',
        'RES',
        'RTE',
        'R',
        'RUL',
        'SEN',
        'SQ',
        'VO',
    ];

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "street_type.too_short", "street_type.too_long" or "street_type.invalid_format"
     */
    public static function of(#[SensitiveParameter] string $value): self
    {
        StringConstraint::length($value, self::SUBJECT, 1, self::MAX_LENGTH);
        StringConstraint::matches($value, self::SUBJECT, self::PATTERN, 'unaccented upper-case letters, apostrophe, hyphen and spaces');

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDocumented(): bool
    {
        return \in_array($this->value, self::DOCUMENTED, true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
