<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Thrown when a tenant records again an external reference with another declaration content or another employer
 * (ADR 0006). The employer number never appears in the message or the parameters (ADR 0007).
 *
 * Violation code: "declaration_record.already_recorded".
 */
final class DeclarationAlreadyRecorded extends DomainException
{
    public static function withExternalReference(ExternalReference $externalReference, DeclarationRecordId $existing): self
    {
        return new self(
            'The external reference is already recorded with another declaration content or employer.',
            'declaration_record.already_recorded',
            ['external_reference' => $externalReference->value(), 'declaration_record_id' => $existing->value()],
        );
    }
}
