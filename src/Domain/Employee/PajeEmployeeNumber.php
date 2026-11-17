<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;

/**
 * Pajemploi employee number `numeroPajeSalarie`: exactly 14 characters (Swagger `minLength` = `maxLength` = 14), no
 * published character set (api-reference §5).
 * Personal data: masked in debug output, no string conversion (ADR 0007).
 */
final readonly class PajeEmployeeNumber
{
    public const LENGTH = 14;
    private const SUBJECT = 'employee_number';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "employee_number.too_short" or "employee_number.too_long"
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
