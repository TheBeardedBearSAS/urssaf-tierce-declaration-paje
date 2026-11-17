<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Reads declaration records of a tenant by external references or by record ids (unknown ones are left out), or lists
 * the ones in a state (least recently updated first, limit capped by the workflow policy).
 */
final readonly class FindDeclarations
{
    /**
     * @param list<ExternalReference>   $externalReferences
     * @param list<DeclarationRecordId> $recordIds
     */
    private function __construct(
        public TenantId $tenantId,
        public array $externalReferences,
        public array $recordIds,
        public ?DeclarationState $state,
        public ?int $limit,
    ) {}

    public static function byExternalReferences(TenantId $tenantId, ExternalReference ...$externalReferences): self
    {
        return new self($tenantId, array_values($externalReferences), [], null, null);
    }

    public static function byRecordIds(TenantId $tenantId, DeclarationRecordId ...$recordIds): self
    {
        return new self($tenantId, [], array_values($recordIds), null, null);
    }

    public static function inState(TenantId $tenantId, DeclarationState $state, ?int $limit): self
    {
        return new self($tenantId, [], [], $state, $limit);
    }
}
