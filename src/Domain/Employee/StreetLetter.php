<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;

/**
 * Letter attached to the street number `lettreVoie`: exactly one character matching the Swagger pattern
 * `[A-Z'\-\s]` (anchored here, the Swagger pattern is unanchored and could never fail). The PDF documents the letters
 * `A` to `Z` (§3.3); other characters of the pattern are accepted and reported by {@see self::isDocumented()}.
 * Part of an employee address: personal data, masked by {@see Address}.
 */
final readonly class StreetLetter
{
    private const SUBJECT = 'street_letter';
    private const PATTERN = '/^[A-Z\'\-\s]$/D';
    private const DOCUMENTED = '/^[A-Z]$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "street_letter.too_short", "street_letter.too_long" or "street_letter.invalid_format"
     */
    public static function of(#[SensitiveParameter] string $value): self
    {
        StringConstraint::length($value, self::SUBJECT, 1, 1);
        StringConstraint::matches($value, self::SUBJECT, self::PATTERN, 'unaccented upper-case letter, apostrophe, hyphen or space');

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDocumented(): bool
    {
        return preg_match(self::DOCUMENTED, $this->value) === 1;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
