<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;

/**
 * Pajemploi employer number `numeroPajeEmployeur` ("pseudo-siret"): exactly 14 characters (Swagger `minLength` = `maxLength` = 14).
 * "Alphanumeric" is only in the description and no regex is published (api-reference §5), so no character set is
 * enforced (§4 decision 11).
 * Personal data: masked in debug output, no string conversion (ADR 0007).
 */
final readonly class PajeEmployerNumber
{
    public const LENGTH = 14;
    private const SUBJECT = 'employer_number';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "employer_number.too_short" or "employer_number.too_long"
     */
    public static function of(#[SensitiveParameter] string $value): self
    {
        StringConstraint::length($value, self::SUBJECT, self::LENGTH, self::LENGTH);

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

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
