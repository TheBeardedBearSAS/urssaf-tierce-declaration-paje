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

/**
 * Opaque fingerprint of the content of a declaration (SHA-256, 64 lowercase hexadecimal characters), used for
 * idempotency (ADR 0006 "same content"). Computed from the declaration by {@see self::of()}; it holds no personal
 * data and ignores the employee identification, the children identity and the employer.
 */
final readonly class DeclarationFingerprint
{
    private const PATTERN = '/^[0-9a-f]{64}$/D';

    private function __construct(private string $value) {}

    /**
     * SHA-256 of the canonical form of the declaration without personal data.
     */
    public static function of(Declaration $declaration): self
    {
        return new self(CanonicalForm::fingerprint($declaration));
    }

    /**
     * @throws InvalidValue "declaration_fingerprint.invalid_format"
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat('declaration_fingerprint', '64 lowercase hexadecimal characters');
        }

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
}
