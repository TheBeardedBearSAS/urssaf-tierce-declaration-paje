<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Closure;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Account repository that lets another writer act just before a save (a status refresh landing between the read and the
 * save of a use case), a given number of times.
 */
final class ConcurrentAccounts implements EmployerAccountRepository
{
    private int $remaining = 0;

    /** @var (Closure(): void)|null */
    private ?Closure $intrusion = null;

    /**
     * @param SnapshottingTransactionManager|null $transactions the intrusion is another writer committing in the middle
     *                                                          of the transaction: its changes survive a rollback
     */
    public function __construct(
        private readonly EmployerAccountRepository $inner,
        private readonly ?SnapshottingTransactionManager $transactions,
    ) {}

    /**
     * @param Closure(): void $intrusion
     */
    public function beforeSave(Closure $intrusion, int $times = 1): void
    {
        $this->intrusion = $intrusion;
        $this->remaining = $times;
    }

    #[Override]
    public function save(EmployerAccount $account): void
    {
        if ($this->intrusion instanceof Closure && $this->remaining > 0) {
            --$this->remaining;
            $intrusion = $this->intrusion;
            $this->transactions instanceof SnapshottingTransactionManager ? $this->transactions->outside($intrusion) : $intrusion();
        }
        $this->inner->save($account);
    }

    #[Override]
    public function get(EmployerAccountId $id): EmployerAccount
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function findByEmployerNumber(TenantId $tenantId, PajeEmployerNumber $employerNumber): ?EmployerAccount
    {
        return $this->inner->findByEmployerNumber($tenantId, $employerNumber);
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?EmployerAccount
    {
        return $this->inner->findByExternalReference($tenantId, $externalReference);
    }

    #[Override]
    public function findLeastRecentlyChecked(TenantId $tenantId, int $limit): array
    {
        return $this->inner->findLeastRecentlyChecked($tenantId, $limit);
    }

    #[Override]
    public function tenants(): array
    {
        return $this->inner->tenants();
    }

    #[Override]
    public function delete(EmployerAccountId $id): void
    {
        $this->inner->delete($id);
    }
}
