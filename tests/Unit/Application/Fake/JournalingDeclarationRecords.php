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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;

/**
 * Decorator over the production in-memory repository: journals every save ("save:<id>"), every won claim
 * ("claim:<id>") and every delete ("delete:<id>"); a claim can be made to lose, like another worker winning it.
 *
 * One method per method of the port.
 *
 * @mago-expect lint:too-many-methods
 */
final class JournalingDeclarationRecords implements DeclarationRecordRepository
{
    private readonly InMemoryDeclarationRecordRepository $inner;

    /** @var list<string> */
    private array $refusedClaims = [];

    /** @var (Closure(InMemoryDeclarationRecordRepository): void)|null */
    private ?Closure $intrusion = null;

    /** @var SnapshottingTransactionManager|null another writer commits in the middle of the transaction: its changes survive a rollback */
    private ?SnapshottingTransactionManager $transactions = null;

    public function __construct(private readonly Journal $journal)
    {
        $this->inner = new InMemoryDeclarationRecordRepository();
    }

    /**
     * The transaction manager the intrusions of {@see self::beforeNextSave()} are committed through.
     */
    public function withTransactions(SnapshottingTransactionManager $transactions): void
    {
        $this->transactions = $transactions;
    }

    public function inner(): InMemoryDeclarationRecordRepository
    {
        return $this->inner;
    }

    /**
     * Stores without journaling (test setup).
     */
    public function add(DeclarationRecord ...$records): void
    {
        foreach ($records as $record) {
            $this->inner->save($record);
        }
    }

    /**
     * Simulates another worker winning the claim: claimForSubmission() returns false for this record.
     */
    public function refuseClaim(DeclarationRecordId $id): void
    {
        $this->refusedClaims[] = $id->value();
    }

    /**
     * Runs $intrusion once, just before the next save reaches the store: another process writing in between (the save
     * of the handler then meets a changed or an already recorded aggregate).
     *
     * @param Closure(InMemoryDeclarationRecordRepository): void $intrusion
     */
    public function beforeNextSave(Closure $intrusion): void
    {
        $this->intrusion = $intrusion;
    }

    #[Override]
    public function save(DeclarationRecord $record): void
    {
        $this->journal->add('save:' . $record->id()->value());
        if ($this->intrusion instanceof Closure) {
            $intrusion = $this->intrusion;
            $this->intrusion = null;
            $inner = $this->inner;
            $this->transactions instanceof SnapshottingTransactionManager
                ? $this->transactions->outside(static fn() => $intrusion($inner))
                : $intrusion($inner);
        }
        $this->inner->save($record);
    }

    #[Override]
    public function get(DeclarationRecordId $id): DeclarationRecord
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?DeclarationRecord
    {
        return $this->inner->findByExternalReference($tenantId, $externalReference);
    }

    #[Override]
    public function findByState(TenantId $tenantId, DeclarationState $state, int $limit): array
    {
        return $this->inner->findByState($tenantId, $state, $limit);
    }

    #[Override]
    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return $this->inner->findStaleSubmissions($olderThan, $limit);
    }

    #[Override]
    public function countByState(TenantId $tenantId): array
    {
        return $this->inner->countByState($tenantId);
    }

    #[Override]
    public function tenantsWithState(DeclarationState $state): array
    {
        return $this->inner->tenantsWithState($state);
    }

    #[Override]
    public function claimForSubmission(DeclarationRecordId $id, Instant $now): bool
    {
        if (\in_array($id->value(), $this->refusedClaims, true)) {
            return false;
        }
        $claimed = $this->inner->claimForSubmission($id, $now);
        if ($claimed) {
            $this->journal->add('claim:' . $id->value());
        }

        return $claimed;
    }

    #[Override]
    public function delete(DeclarationRecordId $id): void
    {
        $this->journal->add('delete:' . $id->value());
        $this->inner->delete($id);
    }

    #[Override]
    public function countByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber): array
    {
        return $this->inner->countByEmployer($tenantId, $employerNumber);
    }

    #[Override]
    public function deleteByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber, array $states): array
    {
        return $this->inner->deleteByEmployer($tenantId, $employerNumber, $states);
    }
}
