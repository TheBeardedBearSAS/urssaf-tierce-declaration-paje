<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Signed amount in euro, stored as an integer number of cents (ADR 0008: wire amounts are JSON numbers with
 * `multipleOf: 0.01`, never floats in the domain).
 *
 * The type carries no bound: each request value object applies the Swagger `minimum` / `maximum` of its field, and
 * response amounts are never rejected (ADR 0009).
 *
 * One method per sign predicate and per comparison, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class Amount
{
    private const SUBJECT = 'amount';
    private const DECIMALS = 2;
    private const MAX_INTEGER_DIGITS = 15;

    private function __construct(private int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Strict parsing of "12", "12.5", "12.50" or "-0.50" (optional leading "-", at most 2 decimals, "." separator).
     *
     * @throws InvalidValue "amount.invalid_format", "amount.too_many_decimals" or "amount.too_large"
     */
    public static function fromDecimalString(string $amount): self
    {
        return new self(Decimal::parseSigned($amount, self::SUBJECT, self::DECIMALS, self::MAX_INTEGER_DIGITS));
    }

    public function cents(): int
    {
        return $this->cents;
    }

    /**
     * Always 2 decimals: "12.50", "0.05", "-0.50".
     */
    public function toDecimalString(): string
    {
        return Decimal::format($this->cents, self::DECIMALS, self::DECIMALS);
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->cents >= $other->cents;
    }

    public function isLessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function isLessThanOrEqual(self $other): bool
    {
        return $this->cents <= $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * @throws InvalidValue "amount.too_large" when the sum does not fit in an integer number of cents
     */
    public function add(self $other): self
    {
        // A bound on the wrong side of the other operand's sign becomes a float outside the integer range, which no
        // integer exceeds, so each comparison can only be true on its own side.
        if ($this->cents > PHP_INT_MAX - $other->cents || $this->cents < PHP_INT_MIN - $other->cents) {
            throw InvalidValue::tooLarge(self::SUBJECT);
        }

        return new self($this->cents + $other->cents);
    }
}
