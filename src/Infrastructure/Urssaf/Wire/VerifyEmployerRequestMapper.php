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
 * 020 `GET /employeurs/{numeroPajeEmployeur}/verifier` (api-reference §2.1): path {@see ApiPaths::verifyEmployer()},
 * query `dtNaissanceEmployeur`, no body.
 */
final class VerifyEmployerRequestMapper
{
    /**
     * @return array{dtNaissanceEmployeur: string}
     */
    public static function query(#[SensitiveParameter] EmployerReference $employer): array
    {
        return EmployerQueryMapper::query($employer);
    }
}
