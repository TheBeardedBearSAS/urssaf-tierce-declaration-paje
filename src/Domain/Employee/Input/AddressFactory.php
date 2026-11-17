<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Address;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\StreetType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw address of an employee into an {@see Address}, reporting every violation (notification pattern,
 * ADR 0000 item 4). The rules are those of the {@see Address} aggregate (Swagger bounds only, ADR 0009).
 *
 * Input keys: "street_number" (integer), "street_letter", "street_type", "street_name", "location_complement",
 * "locality", "municipality_code" (required), "postal_code" (required).
 */
final readonly class AddressFactory
{
    /**
     * Stand-ins for the required fields that are missing, so that the rules of the other fields are still reported.
     * The address built with them is never returned: a missing field is a violation.
     */
    private const MISSING_MUNICIPALITY_CODE = '00000';
    private const MISSING_POSTAL_CODE = '00000';

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
    public function fromInput(#[SensitiveParameter] array $input): Address
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    /**
     * @internal
     */
    public function parse(RawFields $fields): ?Address
    {
        $errorsBefore = $fields->errorCount();
        $streetNumber = $fields->integer('street_number');
        $streetLetter = $fields->optional('street_letter', StreetLetter::of(...));
        $streetType = $fields->optional('street_type', StreetType::of(...));
        $streetName = $fields->text('street_name');
        $locationComplement = $fields->text('location_complement');
        $locality = $fields->text('locality');
        $municipalityCode = $fields->requiredText('municipality_code');
        $postalCode = $fields->required('postal_code', PostalCode::fromString(...));
        $address = $fields->build('', static fn(): Address => Address::of(
            $streetNumber,
            $streetLetter,
            $streetType,
            $streetName,
            $locationComplement,
            $locality,
            $municipalityCode ?? self::MISSING_MUNICIPALITY_CODE,
            $postalCode ?? PostalCode::fromString(self::MISSING_POSTAL_CODE),
        ));

        return $fields->errorCount() > $errorsBefore ? null : $address;
    }
}
