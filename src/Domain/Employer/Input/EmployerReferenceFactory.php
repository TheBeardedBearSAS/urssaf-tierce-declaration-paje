<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw employer context of a host input into an {@see EmployerReference}, reporting every violation
 * (notification pattern, ADR 0000 item 4).
 *
 * Input keys: "employer_number" (`numeroPajeEmployeur`), "birth_date" (`dtNaissanceEmployeur`, Y-m-d).
 */
final readonly class EmployerReferenceFactory
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
    public function fromInput(#[SensitiveParameter] array $input): EmployerReference
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    private function parse(RawFields $fields): ?EmployerReference
    {
        $number = $fields->required('employer_number', PajeEmployerNumber::of(...));
        $birthDate = $fields->required('birth_date', CalendarDate::fromString(...));

        return $number instanceof PajeEmployerNumber && $birthDate instanceof CalendarDate
            ? EmployerReference::of($number, $birthDate)
            : null;
    }
}
