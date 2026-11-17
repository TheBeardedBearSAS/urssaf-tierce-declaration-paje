<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Non-negative number of days that is a multiple of 0.5, stored as an integer number of half-days (api-reference §2.7,
 * e.g. the number of paid-leave days).
 */
final readonly class HalfDays
{
    private const SUBJECT = 'half_days';
    private const DECIMALS = 1;
    private const MAX_INTEGER_DIGITS = 9;
    private const TENTHS_PER_HALF_DAY = 5;
    private const HALF_DAYS_PER_DAY = 2;

    private function __construct(private int $halfDays) {}

    /**
     * @throws InvalidValue "half_days.negative"
     */
    public static function fromHalfDays(int $halfDays): self
    {
        if ($halfDays < 0) {
            throw InvalidValue::negative(self::SUBJECT);
        }

        return new self($halfDays);
    }

    /**
     * Strict parsing of "2", "2.0" or "2.5" (a multiple of 0.5, "." separator, no sign).
     *
     * @throws InvalidValue "half_days.invalid_format", "half_days.negative", "half_days.too_many_decimals",
     *                      "half_days.too_large" or "half_days.not_multiple_of"
     */
    public static function fromDecimalString(string $days): self
    {
        $tenths = Decimal::parse($days, self::SUBJECT, self::DECIMALS, self::MAX_INTEGER_DIGITS);
        if ($tenths % self::TENTHS_PER_HALF_DAY !== 0) {
            throw InvalidValue::notMultipleOf(self::SUBJECT, '0.5');
        }

        return new self(intdiv($tenths, self::TENTHS_PER_HALF_DAY));
    }

    public function halfDays(): int
    {
        return $this->halfDays;
    }

    /**
     * Shortest form: "0", "2", "2.5".
     */
    public function toDecimalString(): string
    {
        $days = (string) intdiv($this->halfDays, self::HALF_DAYS_PER_DAY);

        return $this->halfDays % self::HALF_DAYS_PER_DAY === 0 ? $days : $days . '.5';
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->halfDays > $other->halfDays;
    }

    public function equals(self $other): bool
    {
        return $this->halfDays === $other->halfDays;
    }

    /**
     * Checks a contract maximum and returns the same value.
     *
     * @throws InvalidValue "<subject>.out_of_range" (from "0" to the maximum)
     */
    public function assertAtMost(self $max, string $subject): self
    {
        if ($this->isGreaterThan($max)) {
            throw InvalidValue::outOfRange($subject, '0', $max->toDecimalString());
        }

        return $this;
    }
}
