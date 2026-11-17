<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Name or first name as the Swagger asks for it (`nomEnfant`, `prenomEnfant`, `nomNaissance`, `nomUsage`, `prenom`):
 * at most 30 characters among unaccented upper-case letters, apostrophe, hyphen and whitespace. The Swagger sets no
 * `minLength`, so the empty string is accepted; its pattern is unanchored, but its description ("alphabétique, non
 * accentué et en majuscule") makes clear it applies to the whole value, so it is anchored here.
 *
 * Personal data: masked in debug output, no string conversion.
 */
final readonly class UpperCaseName
{
    public const MAX_LENGTH = 30;
    private const PATTERN = '/^[A-Z\'\-\s]*$/D';

    private function __construct(private string $value) {}

    /**
     * @param string $subject snake_case name used in the violation codes (e.g. "child_last_name")
     *
     * @throws InvalidValue "<subject>.too_long" or "<subject>.invalid_format"
     */
    public static function of(#[SensitiveParameter] string $value, string $subject): self
    {
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong($subject, self::MAX_LENGTH);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat($subject, 'unaccented upper-case letters, apostrophe, hyphen and spaces');
        }

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
