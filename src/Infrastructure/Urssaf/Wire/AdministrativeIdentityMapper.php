<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * `InputIdentificationSalarie`: the optional body of 021 and `inputCreationSalarie.inputIdentificationSalarie` of 023
 * (api-reference §2.2). Keys in the (alphabetical) order of the official example, only the fields that are set. The
 * result is a {@see JsonObject}: an identity that sets no field is `{}`, never `[]` (ADR 0002).
 */
final class AdministrativeIdentityMapper
{
    public static function encode(#[SensitiveParameter] AdministrativeIdentity $identity): JsonObject
    {
        $birthDate = $identity->birthDate();

        return JsonObject::of(array_filter([
            'cdCivilite' => $identity->civility()?->value(),
            'codePaysNaissance' => $identity->birthCountryCode(),
            'departementNaissance' => $identity->birthDepartment(),
            'dtNaissanceSalarie' => $birthDate instanceof CalendarDate ? DateEncoding::encode($birthDate) : null,
            'nomNaissance' => $identity->birthName()?->value(),
            'nomUsage' => $identity->usageName()?->value(),
            'prenom' => $identity->firstName()?->value(),
            'villeNaissance' => $identity->birthCity(),
        ], static fn(?string $value): bool => $value !== null));
    }
}
