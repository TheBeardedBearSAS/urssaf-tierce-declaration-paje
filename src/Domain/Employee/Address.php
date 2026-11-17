<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use Closure;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Postal address of an employee `InputAdresse` (023, api-reference §2.4). Only the Swagger constraints are checked
 * (ADR 0009): `numVoie` 1-9999, `libelleVoie` at most 21 characters, `complementLocalisation` and `lieuDit` at most 32
 * characters of `[A-Z'\-\s]`, `codeCommune` exactly 5 characters. "libelleVoie required with numVoie" and "at least one
 * address line" are PDF-only rules left to the server. Personal data: masked in debug output.
 */
final readonly class Address
{
    public const STREET_NUMBER_MIN = 1;
    public const STREET_NUMBER_MAX = 9999;
    public const STREET_NAME_MAX_LENGTH = 21;
    public const LINE_MAX_LENGTH = 32;
    public const MUNICIPALITY_CODE_LENGTH = 5;
    private const LINE_PATTERN = '/^[A-Z\'\-\s]*$/D';

    private function __construct(
        private ?int $streetNumber,
        private ?StreetLetter $streetLetter,
        private ?StreetType $streetType,
        private ?string $streetName,
        private ?string $locationComplement,
        private ?string $locality,
        private string $municipalityCode,
        private PostalCode $postalCode,
    ) {}

    /**
     * An empty `$streetName`, `$locationComplement` or `$locality` is treated as absent.
     *
     * @param int|null    $streetNumber       `numVoie`
     * @param string|null $streetName         `libelleVoie`
     * @param string|null $locationComplement `complementLocalisation`
     * @param string|null $locality           `lieuDit`
     * @param string      $municipalityCode   `codeCommune` (INSEE)
     *
     * @throws ValidationFailed every violation, paths "streetNumber", "streetName", "locationComplement", "locality",
     *                          "municipalityCode"
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        #[SensitiveParameter]
        ?int $streetNumber,
        ?StreetLetter $streetLetter,
        ?StreetType $streetType,
        #[SensitiveParameter]
        ?string $streetName,
        #[SensitiveParameter]
        ?string $locationComplement,
        #[SensitiveParameter]
        ?string $locality,
        #[SensitiveParameter]
        string $municipalityCode,
        PostalCode $postalCode,
    ): self {
        $collector = new ViolationCollector();
        $number = $collector->optionalInteger(
            'streetNumber',
            'street_number',
            $streetNumber,
            self::STREET_NUMBER_MIN,
            self::STREET_NUMBER_MAX,
        );
        $name = self::text($collector, 'streetName', $streetName, static function (string $value): void {
            StringConstraint::atMost($value, 'street_name', self::STREET_NAME_MAX_LENGTH);
        });
        $complement = self::text($collector, 'locationComplement', $locationComplement, static function (string $value): void {
            self::line('location_complement', $value);
        });
        $place = self::text($collector, 'locality', $locality, static function (string $value): void {
            self::line('locality', $value);
        });
        try {
            StringConstraint::length($municipalityCode, 'municipality_code', self::MUNICIPALITY_CODE_LENGTH, self::MUNICIPALITY_CODE_LENGTH);
        } catch (InvalidValue $invalidValue) {
            $collector->add($invalidValue->toViolation('municipalityCode'));
        }
        $collector->result()->throwIfInvalid();

        return new self($number, $streetLetter, $streetType, $name, $complement, $place, $municipalityCode, $postalCode);
    }

    public function streetNumber(): ?int
    {
        return $this->streetNumber;
    }

    public function streetLetter(): ?StreetLetter
    {
        return $this->streetLetter;
    }

    public function streetType(): ?StreetType
    {
        return $this->streetType;
    }

    public function streetName(): ?string
    {
        return $this->streetName;
    }

    public function locationComplement(): ?string
    {
        return $this->locationComplement;
    }

    public function locality(): ?string
    {
        return $this->locality;
    }

    public function municipalityCode(): string
    {
        return $this->municipalityCode;
    }

    public function postalCode(): PostalCode
    {
        return $this->postalCode;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    /**
     * @param Closure(string): void $assert
     *
     * @param-immediately-invoked-callable $assert
     */
    private static function text(ViolationCollector $collector, string $path, ?string $value, Closure $assert): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $assert($value);
        } catch (InvalidValue $invalidValue) {
            $collector->add($invalidValue->toViolation($path));
        }

        return $value;
    }

    private static function line(string $subject, string $value): void
    {
        StringConstraint::atMost($value, $subject, self::LINE_MAX_LENGTH);
        StringConstraint::matches(
            $value,
            $subject,
            self::LINE_PATTERN,
            'unaccented upper-case letters, apostrophe, hyphen and spaces',
        );
    }
}
