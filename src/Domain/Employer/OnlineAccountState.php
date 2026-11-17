<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

/**
 * Online account state `etatCompteEnLigne` of an employer (020, "Actif", "Absent", "Inactif", "Coordonnées bancaires
 * invalides") or an employee (021, 023). The raw string is always kept; {@see self::documented()} is null for a value
 * the specification does not list (api-reference §5).
 */
final readonly class OnlineAccountState
{
    private function __construct(private string $raw) {}

    public static function of(string $raw): self
    {
        return new self($raw);
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function documented(): ?OnlineAccountStateValue
    {
        return OnlineAccountStateValue::tryFrom(trim($this->raw));
    }

    public function equals(self $other): bool
    {
        return $this->raw === $other->raw;
    }
}
