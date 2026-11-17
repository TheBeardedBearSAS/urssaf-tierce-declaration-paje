<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Settles a declaration whose outcome is unknown after the operator checked it on pajemploi.urssaf.fr (ADR 0003): either
 * URSSAF holds it ({@see self::asDeclared()}, with its `referenceDocumentaire`) or it never received it
 * ({@see self::asNotReceived()}: the record is pending again, the host declares the salary again).
 */
final readonly class ResolveDeclarationOutcome
{
    private function __construct(
        public TenantId $tenantId,
        public DeclarationSelector $declaration,
        public ?string $documentReference,
    ) {}

    public static function asDeclared(TenantId $tenantId, DeclarationSelector $declaration, string $documentReference): self
    {
        return new self($tenantId, $declaration, $documentReference);
    }

    public static function asNotReceived(TenantId $tenantId, DeclarationSelector $declaration): self
    {
        return new self($tenantId, $declaration, null);
    }
}
