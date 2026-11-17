<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;

/**
 * Employer context of 020, 022, 023, 045, 046, 050 and 051 (api-reference §2): `numeroPajeEmployeur` is a path segment, see
 * {@see ApiPaths::employer()}; `dtNaissanceEmployeur` is a query parameter. The mandates (030, 031) use other names,
 * see {@see MandateRequestMapper}.
 */
final class EmployerQueryMapper
{
    /**
     * @return array{dtNaissanceEmployeur: string}
     */
    public static function query(#[SensitiveParameter] EmployerReference $employer): array
    {
        return ['dtNaissanceEmployeur' => DateEncoding::encode($employer->birthDate())];
    }
}
