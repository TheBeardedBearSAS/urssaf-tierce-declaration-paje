<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Postal code of an employer or an employee (`cdPostalEmployeur`, `cdPostalSalarie`, `codePostal`): exactly 5
 * characters (Swagger `minLength` = `maxLength` = 5; the "numeric" wording is only in the description, so no pattern
 * is enforced, api-reference §4 decision 11). Not personal data by itself.
 */
final readonly class PostalCode
{
    public const LENGTH = 5;
    private const SUBJECT = 'postal_code';

    private function __construct(private string $value) {}

    /**
     * @param string $subject snake_case name used in the violation codes, so that callers can tell the fields apart
     *
     * @throws InvalidValue "<subject>.too_short" or "<subject>.too_long"
     */
    public static function fromString(#[SensitiveParameter] string $value, string $subject = self::SUBJECT): self
    {
        StringConstraint::length($value, $subject, self::LENGTH, self::LENGTH);

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
