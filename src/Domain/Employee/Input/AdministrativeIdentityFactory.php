<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Civility;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw administrative data of an employee into an {@see AdministrativeIdentity}, reporting every violation
 * (notification pattern, ADR 0000 item 4). Every field is optional (ADR 0009); an identity without any field is valid
 * here and refused as an identification criterion by {@see EmployeeIdentificationFactory}.
 *
 * Input keys: "civility", "birth_name", "usage_name", "first_name", "birth_date" (Y-m-d), "birth_country_code",
 * "birth_department", "birth_city".
 */
final readonly class AdministrativeIdentityFactory
{
    /**
     * @param array<array-key, mixed> $input
     */
    public function validate(#[SensitiveParameter] array $input): ValidationResult
    {
        return RawFields::validate($input, $this->parse(...));
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function fromInput(#[SensitiveParameter] array $input): AdministrativeIdentity
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    /**
     * @internal
     */
    public function parse(RawFields $fields): ?AdministrativeIdentity
    {
        $errorsBefore = $fields->errorCount();
        $civility = $fields->optional('civility', Civility::of(...));
        $birthName = $fields->optional('birth_name', static fn(string $raw): UpperCaseName => UpperCaseName::of($raw, 'birth_name'));
        $usageName = $fields->optional('usage_name', static fn(string $raw): UpperCaseName => UpperCaseName::of($raw, 'usage_name'));
        $firstName = $fields->optional('first_name', static fn(string $raw): UpperCaseName => UpperCaseName::of($raw, 'first_name'));
        $birthDate = $fields->optional('birth_date', CalendarDate::fromString(...));
        $birthCountryCode = $fields->text('birth_country_code');
        $birthDepartment = $fields->text('birth_department');
        $birthCity = $fields->text('birth_city');
        $identity = $fields->build('', static fn(): AdministrativeIdentity => AdministrativeIdentity::of(
            $civility,
            $birthName,
            $usageName,
            $firstName,
            $birthDate,
            $birthCountryCode,
            $birthDepartment,
            $birthCity,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $identity;
    }
}
