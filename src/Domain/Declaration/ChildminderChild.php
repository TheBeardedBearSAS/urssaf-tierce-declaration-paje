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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Child of a childminder declaration (`InputEnfantAma`: the child plus `joursAccueilHandicap` and
 * `joursAccueil24Consecutive`). The Swagger only types both counts as `int32`; every PDF rule on them (equal to the
 * activity days for a disabled child, not above the activity days) is server-side (ADR 0009). The int32 range is
 * enforced (ADR 0009: `format: int32` is a bound when the schema has no explicit one).
 *
 * `joursAccueilHandicap` reveals a child's disability (health data): masked in debug output, like the child itself.
 *
 * Violation paths: "disabledChildCareDays", "consecutive24hDays".
 */
final readonly class ChildminderChild
{
    private const INT32_MIN = -2_147_483_648;
    private const INT32_MAX = 2_147_483_647;

    private function __construct(
        private Child $child,
        private ?int $disabledChildCareDays,
        private ?int $consecutive24hDays,
    ) {}

    /**
     * @param int|null $disabledChildCareDays `joursAccueilHandicap`
     * @param int|null $consecutive24hDays    `joursAccueil24Consecutive`
     *
     * @throws ValidationFailed with every violation
     */
    public static function of(Child $child, ?int $disabledChildCareDays, ?int $consecutive24hDays): self
    {
        $collector = new ViolationCollector();
        $collector->optionalInteger(
            'disabledChildCareDays',
            'disabled_child_care_days',
            $disabledChildCareDays,
            self::INT32_MIN,
            self::INT32_MAX,
        );
        $collector->optionalInteger(
            'consecutive24hDays',
            'consecutive_24h_days',
            $consecutive24hDays,
            self::INT32_MIN,
            self::INT32_MAX,
        );
        $collector->result()->throwIfInvalid();

        return new self($child, $disabledChildCareDays, $consecutive24hDays);
    }

    public function child(): Child
    {
        return $this->child;
    }

    /**
     * `joursAccueilHandicap`.
     */
    public function disabledChildCareDays(): ?int
    {
        return $this->disabledChildCareDays;
    }

    /**
     * `joursAccueil24Consecutive`.
     */
    public function consecutive24hDays(): ?int
    {
        return $this->consecutive24hDays;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
