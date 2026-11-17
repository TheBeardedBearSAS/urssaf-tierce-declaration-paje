<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Address;

/**
 * `InputAdresse` of 023 (`inputCreationSalarie.inputAdresseSalarie`, api-reference §2.4). Keys in the (alphabetical)
 * order of the official example; `codeCommune` and `codePostal` are always sent, every other field only when set
 * (`numVoie` as a JSON integer).
 */
final class AddressMapper
{
    /**
     * @return array<string, int|string>
     */
    public static function encode(#[SensitiveParameter] Address $address): array
    {
        $letter = $address->streetLetter();
        $type = $address->streetType();

        return array_filter([
            'codeCommune' => $address->municipalityCode(),
            'codePostal' => $address->postalCode()->value(),
            'complementLocalisation' => $address->locationComplement(),
            'lettreVoie' => $letter?->value(),
            'libelleVoie' => $address->streetName(),
            'lieuDit' => $address->locality(),
            'numVoie' => $address->streetNumber(),
            'typeVoie' => $type?->value(),
        ], static fn(int|string|null $value): bool => $value !== null);
    }
}
