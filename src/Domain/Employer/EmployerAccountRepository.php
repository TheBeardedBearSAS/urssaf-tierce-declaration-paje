<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Persistence port of the {@see EmployerAccount} aggregate. Expected uniqueness: the id, (tenant, employer number) and
 * (tenant, external reference) (ADR 0006, 0010).
 */
interface EmployerAccountRepository
{
    /** Violation code of a save refused because another writer changed the account since it was read. */
    public const CONCURRENT_MODIFICATION = 'employer_account.concurrent_modification';

    /**
     * Inserts or updates. Domain events are not persisted: release and publish them separately.
     *
     * Optimistic concurrency (ADR 0006, ADR 0014): a copy that another writer changed (saved or erased) since it was
     * read is refused. The copy is then stale: read the account again.
     *
     * @throws InvariantViolation "employer_account.concurrent_modification" (see {@see self::CONCURRENT_MODIFICATION}),
     *                            "employer_account.duplicate_employer_number" or
     *                            "employer_account.duplicate_external_reference"
     */
    public function save(EmployerAccount $account): void;

    /**
     * @throws RecordNotFound "employer_account.not_found"
     */
    public function get(EmployerAccountId $id): EmployerAccount;

    public function findByEmployerNumber(TenantId $tenantId, PajeEmployerNumber $employerNumber): ?EmployerAccount;

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?EmployerAccount;

    /**
     * Accounts of the tenant for the status synchronisation (method 020, the only polling): never checked first, then
     * by last check ascending.
     *
     * @param positive-int $limit
     *
     * @return list<EmployerAccount>
     */
    public function findLeastRecentlyChecked(TenantId $tenantId, int $limit): array;

    /**
     * Distinct tenants owning at least one account.
     *
     * @return list<TenantId>
     */
    public function tenants(): array;

    /**
     * GDPR erasure (ADR 0015); nothing happens when the account does not exist.
     */
    public function delete(EmployerAccountId $id): void;
}
