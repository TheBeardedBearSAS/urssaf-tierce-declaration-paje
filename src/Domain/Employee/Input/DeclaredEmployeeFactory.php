<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw `InputSp` of a declaration into a {@see DeclaredEmployee}, reporting every violation (notification
 * pattern, ADR 0000 item 4). The criteria have a priority (api-reference §2.2): when both are given the Pajemploi
 * number is kept, as URSSAF does; each given criterion is still validated. Without any criterion the violation
 * "employee_identification.empty" is reported (api-reference §4 decision 10).
 *
 * Input keys: "employee_number", "nir", "birth_date" (Y-m-d, optional).
 */
final readonly class DeclaredEmployeeFactory
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
    public function fromInput(#[SensitiveParameter] array $input): DeclaredEmployee
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    /**
     * @internal
     */
    public function parse(RawFields $fields): ?DeclaredEmployee
    {
        $errorsBefore = $fields->errorCount();
        $birthDate = $fields->optional('birth_date', CalendarDate::fromString(...));
        $criteriaErrorsBefore = $fields->errorCount();
        $employee = $this->parseCriteria($fields, $birthDate);
        if (!$employee instanceof DeclaredEmployee && $fields->errorCount() === $criteriaErrorsBefore) {
            $fields->add(InvalidValue::empty('employee_identification')->toViolation(''));
        }

        return $fields->errorCount() > $errorsBefore ? null : $employee;
    }

    /**
     * Parses the Pajemploi number and the NIR. Returns null after reporting the violations, or without any violation
     * when neither criterion is given.
     *
     * @internal
     */
    public function parseCriteria(RawFields $fields, #[SensitiveParameter] ?CalendarDate $birthDate): ?DeclaredEmployee
    {
        $number = $fields->optional('employee_number', PajeEmployeeNumber::of(...));
        $nir = $fields->optional('nir', Nir::of(...));
        if ($number instanceof PajeEmployeeNumber) {
            return EmployeeByNumber::of($number, $birthDate);
        }

        return $nir instanceof Nir ? EmployeeByNir::of($nir, $birthDate) : null;
    }
}
