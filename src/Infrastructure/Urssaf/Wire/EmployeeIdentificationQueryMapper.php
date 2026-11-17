<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Query parameters of the employee identification of 021 and 023 (api-reference §2.2, §2.4): `numeroPajeSalarie`
 * (priority 1) or `nirSalarie` (priority 2), then `dtNaissanceSalarie` when the identification carries a birth date.
 *
 * An identification by administrative data contributes no criterion: only its optional birth date. On 021 its identity
 * is the request body ({@see VerifyEmployeeRequestMapper::body()}); on 023 the identity travels only inside the
 * creation body ({@see EmployeeCreationMapper}) and the Application lot must require it to be non-empty then
 * (ADR 0009 amendment), otherwise the request would carry no criterion at all.
 */
final class EmployeeIdentificationQueryMapper
{
    /**
     * @return array<string, string>
     */
    public static function query(#[SensitiveParameter] EmployeeIdentification $identification): array
    {
        $query = [];

        if ($identification instanceof EmployeeByNumber) {
            $query['numeroPajeSalarie'] = $identification->number()->value();
        }

        if ($identification instanceof EmployeeByNir) {
            $query['nirSalarie'] = $identification->nir()->value();
        }

        $birthDate = $identification->birthDate();

        if ($birthDate instanceof CalendarDate) {
            $query['dtNaissanceSalarie'] = DateEncoding::encode($birthDate);
        }

        return $query;
    }
}
