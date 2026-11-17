<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;

/**
 * Paid-leave days (`nbJoursCongesPayes`) are a JSON number `multipleOf: 0.5` (Swagger): emitted in the shortest form,
 * "2" or "2.5", never through a float.
 */
final class HalfDaysEncoding
{
    public static function encode(HalfDays $days): JsonDecimal
    {
        return JsonDecimal::fromString($days->toDecimalString());
    }
}
