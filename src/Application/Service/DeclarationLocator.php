<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Loads the declaration record designated by a {@see DeclarationSelector}, within one tenant.
 */
final readonly class DeclarationLocator
{
    private const RECORD_TYPE = 'declaration_record';

    public function __construct(private DeclarationRecordRepository $records) {}

    /**
     * @throws RecordNotFound "declaration_record.not_found" (also for a record of another tenant)
     */
    public function locate(TenantId $tenantId, DeclarationSelector $selector): DeclarationRecord
    {
        $reference = $selector->reference;
        if ($reference instanceof ExternalReference) {
            return $this->records->findByExternalReference($tenantId, $reference)
                ?? throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }
        $record = $this->records->get($reference);
        if (!$record->tenantId()->equals($tenantId)) {
            throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }

        return $record;
    }
}
