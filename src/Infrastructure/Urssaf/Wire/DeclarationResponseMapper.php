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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * `OutputDeclaration` of 050 / 051 (api-reference §2.9): the fields of {@see PreDeclarationResponseMapper} plus
 * `referenceDocumentaire` (the *volet social*; absent is null, an empty string is kept as is).
 *
 * The caller turns an {@see UnexpectedUrssafResponse} on a 2xx answer into an unknown outcome (ADR 0003): a recorded
 * declaration must not be reported as failed.
 */
final class DeclarationResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public static function map(Payload $payload): DeclarationResult
    {
        $common = PreDeclarationResponseMapper::map($payload);

        return DeclarationResult::of(
            $payload->optionalString('referenceDocumentaire'),
            $common->amounts(),
            $common->isPajemploiPlus(),
            $common->transferDate(),
            $common->childChecks(),
            $common->messages(),
        );
    }
}
