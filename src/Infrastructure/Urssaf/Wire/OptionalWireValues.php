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

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;

/**
 * Request members whose value is optional: a member that is not set is omitted from the body, never sent as `null`
 * (ADR 0002; the Swagger declares these members optional).
 */
final class OptionalWireValues
{
    public static function amount(?Amount $amount): ?JsonDecimal
    {
        return $amount instanceof Amount ? AmountEncoding::encode($amount) : null;
    }

    public static function halfDays(?HalfDays $days): ?JsonDecimal
    {
        return $days instanceof HalfDays ? HalfDaysEncoding::encode($days) : null;
    }

    /**
     * The members that are not null, in their original order.
     *
     * @param array<string, mixed> $members
     *
     * @return array<string, mixed>
     */
    public static function present(#[SensitiveParameter] array $members): array
    {
        return array_filter($members, static fn(mixed $value): bool => $value !== null);
    }
}
