<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Address;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Civility;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\StreetType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;

/**
 * Domain objects equal to `associate_employee_request_valid.json` and to the official 020 / 022 examples.
 */
final class EmployeeWireSamples
{
    private function __construct() {}

    public static function employer(): EmployerReference
    {
        return EmployerReference::of(PajeEmployerNumber::of('Y1869005780009'), CalendarDate::fromString('1975-02-23'));
    }

    public static function child(): Child
    {
        return Child::of(
            CalendarDate::fromString('2015-01-12'),
            UpperCaseName::of("L'HOMME", 'child_last_name'),
            UpperCaseName::of('JEAN-MICHEL', 'child_first_name'),
        );
    }

    public static function address(): Address
    {
        return Address::of(
            12,
            StreetLetter::of('B'),
            StreetType::of('R'),
            'DE LA PAIX',
            'RESIDENCE LES LILAS',
            'LE BOURG',
            '94022',
            PostalCode::fromString('94600'),
        );
    }

    public static function identity(): AdministrativeIdentity
    {
        return AdministrativeIdentity::of(
            Civility::mister(),
            UpperCaseName::of("L'HOMME", 'birth_name'),
            UpperCaseName::of('DURAND', 'usage_name'),
            UpperCaseName::of('JEAN-MICHEL', 'first_name'),
            CalendarDate::fromString('1980-01-30'),
            '100',
            '972',
            'CHOISY LE ROI',
        );
    }

    public static function creation(): EmployeeCreation
    {
        return EmployeeCreation::of(
            ActivityType::childminder(),
            CalendarDate::fromString('1980-01-30'),
            Nir::of('178010701005265'),
            CalendarDate::fromString('1980-01-30'),
            self::address(),
            self::identity(),
        );
    }
}
