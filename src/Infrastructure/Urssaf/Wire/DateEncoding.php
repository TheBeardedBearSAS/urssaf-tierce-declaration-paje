<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Dates of the URSSAF wire contract are date-only `YYYY-MM-DD` strings in both directions (ADR 0001): never a time
 * part, `Z` or offset. The placeholder `"string"` of the official examples is refused.
 */
final class DateEncoding
{
    public static function encode(CalendarDate $date): string
    {
        return $date->toString();
    }

    /**
     * @throws UnexpectedUrssafResponse when the value is not a strict `Y-m-d` calendar date (names the field only)
     */
    public static function decode(#[SensitiveParameter] string $value, string $field): CalendarDate
    {
        try {
            return CalendarDate::fromString($value);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy(\sprintf('field "%s" is not a Y-m-d date', $field), $exception);
        }
    }
}
