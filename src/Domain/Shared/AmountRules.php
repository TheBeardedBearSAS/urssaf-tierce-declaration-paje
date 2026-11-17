<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Swagger bounds of the request amounts (ADR 0008, ADR 0009): `maximum: 9999.99` everywhere, plus a `minimum` of 0 only
 * on `mntIndemnitesEntretien` and a strictly positive value only on `InputDeclCommun.mntSalaireNetMensuel` (a contract rule taken from the Swagger
 * description "Ce champ doit etre superieur a 0", not from a `minimum` keyword; listed in ADR 0009). A missing
 * (null) amount is never a violation here: requiredness is the caller's concern.
 *
 * @internal
 */
final readonly class AmountRules
{
    private const MAX_CENTS = 999_999;

    /**
     * Only the maximum applies: "<subject>.too_large".
     */
    public static function capped(ViolationCollector $collector, string $path, string $subject, ?Amount $amount): void
    {
        if ($amount?->isGreaterThan(Amount::fromCents(self::MAX_CENTS)) === true) {
            $collector->add(InvalidValue::tooLarge($subject)->toViolation($path));
        }
    }

    /**
     * Minimum 0 (inclusive) and maximum: "<subject>.negative" or "<subject>.too_large".
     */
    public static function nonNegativeCapped(
        ViolationCollector $collector,
        string $path,
        string $subject,
        ?Amount $amount,
    ): void {
        if ($amount?->isNegative() === true) {
            $collector->add(InvalidValue::negative($subject)->toViolation($path));
        }
        // A negative amount never exceeds the maximum, so this adds nothing in that case.
        self::capped($collector, $path, $subject, $amount);
    }

    /**
     * Strictly positive and maximum: "<subject>.not_positive" or "<subject>.too_large".
     */
    public static function positiveCapped(
        ViolationCollector $collector,
        string $path,
        string $subject,
        Amount $amount,
    ): void {
        if (!$amount->isPositive()) {
            $collector->add(InvalidValue::notPositive($subject)->toViolation($path));
        }
        // A non-positive amount never exceeds the maximum, so this adds nothing in that case.
        self::capped($collector, $path, $subject, $amount);
    }
}
