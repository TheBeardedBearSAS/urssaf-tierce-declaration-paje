<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;

/**
 * 030 `POST /mandats` and 031 `DELETE /mandats` (api-reference §2.11, §2.12): query `numeroPaje` (the employer number,
 * here a query parameter and not a path segment) and `dateNaissance`, no body.
 */
final class MandateRequestMapper
{
    /**
     * @return array{numeroPaje: string, dateNaissance: string}
     */
    public static function query(#[SensitiveParameter] EmployerReference $employer): array
    {
        return [
            'numeroPaje' => $employer->number()->value(),
            'dateNaissance' => DateEncoding::encode($employer->birthDate()),
        ];
    }
}
