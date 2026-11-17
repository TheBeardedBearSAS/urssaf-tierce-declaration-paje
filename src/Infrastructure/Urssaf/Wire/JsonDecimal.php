<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;

/**
 * A decimal number emitted verbatim in the JSON document (never going through a float), so 11.02 stays 11.02.
 */
final readonly class JsonDecimal
{
    private const PATTERN = '/^-?(0|[1-9][0-9]*)(\.[0-9]+)?$/D';

    private function __construct(private string $literal) {}

    /**
     * @throws InvariantViolation "json_decimal.invalid" when the literal is not a valid JSON number
     */
    public static function fromString(string $literal): self
    {
        if (preg_match(self::PATTERN, $literal) !== 1) {
            throw InvariantViolation::because('json_decimal.invalid', 'The value is not a valid JSON number literal.');
        }

        return new self($literal);
    }

    public function literal(): string
    {
        return $this->literal;
    }
}
