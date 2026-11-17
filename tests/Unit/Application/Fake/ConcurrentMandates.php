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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Mandate repository that lets another writer act just before a save, a given number of times.
 */
final class ConcurrentMandates implements MandateRepository
{
    private int $remaining = 0;

    /** @var (Closure(): void)|null */
    private ?Closure $intrusion = null;

    /**
     * @param SnapshottingTransactionManager|null $transactions the intrusion is another writer committing in the middle
     *                                                          of the transaction: its changes survive a rollback
     */
    public function __construct(
        private readonly MandateRepository $inner,
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
    public function save(Mandate $mandate): void
    {
        if ($this->intrusion instanceof Closure && $this->remaining > 0) {
            --$this->remaining;
            $intrusion = $this->intrusion;
            $this->transactions instanceof SnapshottingTransactionManager ? $this->transactions->outside($intrusion) : $intrusion();
        }
        $this->inner->save($mandate);
    }

    #[Override]
    public function get(MandateId $id): Mandate
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function findActive(TenantId $tenantId, PajeEmployerNumber $employerNumber): ?Mandate
    {
        return $this->inner->findActive($tenantId, $employerNumber);
    }

    #[Override]
    public function findByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber): array
    {
        return $this->inner->findByEmployer($tenantId, $employerNumber);
    }

    #[Override]
    public function delete(MandateId $id): void
    {
        $this->inner->delete($id);
    }
}
