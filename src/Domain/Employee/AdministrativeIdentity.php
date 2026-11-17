<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use Closure;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\StringConstraint;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Administrative identity of an employee `InputIdentificationSalarie` (021 body, 023 body, api-reference §2.2). Every
 * field is optional (the Swagger requires none, the laxer bound, ADR 0009). The names are {@see UpperCaseName}; the
 * country (`codePaysNaissance`) and department (`departementNaissance`) codes hold at most 3 characters and the birth
 * city (`villeNaissance`) at most 30 characters of the PDF pattern (accents allowed, the Swagger pattern is mangled,
 * api-reference §5). Personal data: masked in debug output.
 */
final readonly class AdministrativeIdentity
{
    public const CODE_MAX_LENGTH = 3;
    public const BIRTH_CITY_MAX_LENGTH = 30;
    private const BIRTH_CITY_PATTERN = '/^[a-zA-ZáÁàÀâÂäÄéÉèÈëËêÊìÌíÍïÏîÎñÑòÒóÓôÔöÖùÙúÚüÜûÛçÇýÝÿŸŒœÆæ\'\-\s]*$/Du';

    private function __construct(
        private ?Civility $civility,
        private ?UpperCaseName $birthName,
        private ?UpperCaseName $usageName,
        private ?UpperCaseName $firstName,
        #[SensitiveParameter]
        private ?CalendarDate $birthDate,
        #[SensitiveParameter]
        private ?string $birthCountryCode,
        #[SensitiveParameter]
        private ?string $birthDepartment,
        #[SensitiveParameter]
        private ?string $birthCity,
    ) {}

    /**
     * An empty name, code or city is treated as absent (same rule as {@see Address}), so that an identity made of empty
     * strings is empty and refused as an identification criterion.
     *
     * @param string|null $birthCountryCode `codePaysNaissance`, INSEE code (3 characters at most)
     * @param string|null $birthDepartment  `departementNaissance` (3 characters at most)
     * @param string|null $birthCity        `villeNaissance` (30 characters at most)
     *
     * @throws ValidationFailed every violation, paths "birthCountryCode", "birthDepartment", "birthCity"
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        ?Civility $civility,
        ?UpperCaseName $birthName,
        ?UpperCaseName $usageName,
        ?UpperCaseName $firstName,
        #[SensitiveParameter]
        ?CalendarDate $birthDate,
        #[SensitiveParameter]
        ?string $birthCountryCode,
        #[SensitiveParameter]
        ?string $birthDepartment,
        #[SensitiveParameter]
        ?string $birthCity,
    ): self {
        $collector = new ViolationCollector();
        self::check($collector, 'birthCountryCode', $birthCountryCode, static function (string $value): void {
            StringConstraint::atMost($value, 'birth_country_code', self::CODE_MAX_LENGTH);
        });
        self::check($collector, 'birthDepartment', $birthDepartment, static function (string $value): void {
            StringConstraint::atMost($value, 'birth_department', self::CODE_MAX_LENGTH);
        });
        self::check($collector, 'birthCity', $birthCity, static function (string $value): void {
            StringConstraint::atMost($value, 'birth_city', self::BIRTH_CITY_MAX_LENGTH);
            StringConstraint::matches($value, 'birth_city', self::BIRTH_CITY_PATTERN, 'letters (accents allowed), apostrophe, hyphen and spaces');
        });
        $collector->result()->throwIfInvalid();

        return new self(
            $civility,
            self::absentIfBlank($birthName),
            self::absentIfBlank($usageName),
            self::absentIfBlank($firstName),
            $birthDate,
            self::absentIfEmpty($birthCountryCode),
            self::absentIfEmpty($birthDepartment),
            self::absentIfEmpty($birthCity),
        );
    }

    public function civility(): ?Civility
    {
        return $this->civility;
    }

    public function birthName(): ?UpperCaseName
    {
        return $this->birthName;
    }

    public function usageName(): ?UpperCaseName
    {
        return $this->usageName;
    }

    public function firstName(): ?UpperCaseName
    {
        return $this->firstName;
    }

    public function birthDate(): ?CalendarDate
    {
        return $this->birthDate;
    }

    public function birthCountryCode(): ?string
    {
        return $this->birthCountryCode;
    }

    public function birthDepartment(): ?string
    {
        return $this->birthDepartment;
    }

    public function birthCity(): ?string
    {
        return $this->birthCity;
    }

    /**
     * True when no field is set (the body would be `{}`). Accepted here, refused as an identification criterion
     * ({@see EmployeeByAdministrativeData::of()}).
     */
    public function isEmpty(): bool
    {
        return !$this->civility instanceof Civility
            && !$this->birthName instanceof UpperCaseName
            && !$this->usageName instanceof UpperCaseName
            && !$this->firstName instanceof UpperCaseName
            && !$this->birthDate instanceof CalendarDate
            && $this->birthCountryCode === null
            && $this->birthDepartment === null
            && $this->birthCity === null;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    private static function absentIfBlank(?UpperCaseName $name): ?UpperCaseName
    {
        return $name?->value() === '' ? null : $name;
    }

    private static function absentIfEmpty(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param Closure(string): void $assert
     *
     * @param-immediately-invoked-callable $assert
     */
    private static function check(ViolationCollector $collector, string $path, ?string $value, Closure $assert): void
    {
        if ($value === null) {
            return;
        }
        try {
            $assert($value);
        } catch (InvalidValue $invalidValue) {
            $collector->add($invalidValue->toViolation($path));
        }
    }
}
