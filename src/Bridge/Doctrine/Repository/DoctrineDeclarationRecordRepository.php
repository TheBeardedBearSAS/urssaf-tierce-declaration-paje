<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\DeclarationRecordIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\PajeEmployerNumberType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Doctrine ORM implementation of {@see DeclarationRecordRepository} with the semantics of the in-memory one: lists are
 * ordered by last update (oldest first, then creation, then id) unless the port says otherwise, the uniqueness violation
 * is reported as {@see InvariantViolation} with the same code.
 *
 * Unit of work: {@see self::save()} persists and flushes immediately (a flush writes every dirty managed entity), so a
 * save inside a {@see DoctrineTransactionManager} transaction is durable only when that transaction commits: a
 * "supersede" (delete, then save of a new record with the same external reference, ADR 0006) inside one transaction is
 * atomic. Reads return the managed instance, so unlike the in-memory repository a change is visible to every holder of
 * the instance before it is saved.
 *
 * Claiming: {@see self::claimForSubmission()} is one conditional UPDATE that also bumps the version column, never an
 * object round-trip. Concurrency (see {@see AggregateStore}): a copy that another writer changed, claimed or erased
 * since it was read, and an instance that is not managed while its identifier is stored, are refused with
 * "declaration_record.concurrent_modification", before any flush.
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness, claim and erasure helpers,
 * hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class DoctrineDeclarationRecordRepository implements DeclarationRecordRepository
{
    private const ENTITY = DeclarationRecord::class;

    private const BY_UPDATE = ' ORDER BY r.status.updatedAt ASC, r.createdAt ASC, r.id ASC';

    /** @var AggregateStore<DeclarationRecord> */
    private AggregateStore $store;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->store = new AggregateStore(
            $entityManager,
            self::ENTITY,
            DeclarationRecordIdType::NAME,
            self::CONCURRENT_MODIFICATION,
            'The declaration record was changed by another writer since it was read: read it again.',
        );
    }

    /**
     * @throws InvariantViolation "declaration_record.concurrent_modification" or
     *                            "declaration_record.duplicate_external_reference"
     */
    #[Override]
    public function save(DeclarationRecord $record): void
    {
        $this->store->save($record, $record->id(), $this->assertUnique(...), static function (): void {});
    }

    #[Override]
    public function get(DeclarationRecordId $id): DeclarationRecord
    {
        return $this->store->find($id) ?? throw RecordNotFound::withIdentifier(
            'declaration_record',
            $id->value(),
        );
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?DeclarationRecord
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery(
                    'SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.externalReference = :reference',
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('reference', $externalReference, ExternalReferenceType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findByState(TenantId $tenantId, DeclarationState $state, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.status.state = :state'
                    . self::BY_UPDATE,
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('state', $state)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT r FROM ' . self::ENTITY . ' r WHERE r.status.state = :submitting'
                    . ' AND r.status.lastAttemptAt <= :olderThan'
                    . ' ORDER BY r.status.lastAttemptAt ASC, r.createdAt ASC, r.id ASC',
                )
                ->setParameter('submitting', DeclarationState::Submitting)
                ->setParameter('olderThan', $olderThan, InstantType::NAME)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function countByState(TenantId $tenantId): array
    {
        return $this->counts(
            'SELECT r.status.state AS state, COUNT(r.id) AS total FROM ' . self::ENTITY
            . ' r WHERE r.tenantId = :tenant GROUP BY r.status.state',
            $tenantId,
            null,
        );
    }

    #[Override]
    public function tenantsWithState(DeclarationState $state): array
    {
        /** @var list<string> $tenants */
        $tenants = $this->entityManager
            ->createQuery(
                'SELECT DISTINCT r.tenantId FROM ' . self::ENTITY
                . ' r WHERE r.status.state = :state ORDER BY r.tenantId ASC',
            )
            ->setParameter('state', $state)
            ->getSingleColumnResult();

        return array_map(TenantId::fromString(...), $tenants);
    }

    /**
     * Compare-and-set pending -> submitting in one UPDATE, like {@see DeclarationRecord::startSubmission()}, bumping
     * the version so that a copy read before the claim is refused at its next save.
     */
    #[Override]
    public function claimForSubmission(DeclarationRecordId $id, Instant $now): bool
    {
        $claimed = $this->entityManager
            ->createQuery(
                'UPDATE ' . self::ENTITY . ' r SET r.status.state = :submitting,'
                . ' r.status.attempts = r.status.attempts + 1, r.status.lastAttemptAt = :now,'
                . ' r.status.updatedAt = :now, r.status.outcomeUnknownReason = NULL, r.version = r.version + 1'
                . ' WHERE r.id = :id AND r.status.state = :pending',
            )
            ->setParameter('submitting', DeclarationState::Submitting)
            ->setParameter('pending', DeclarationState::Pending)
            ->setParameter('now', $now, InstantType::NAME)
            ->setParameter('id', $id, DeclarationRecordIdType::NAME)
            ->execute() === 1;
        if ($claimed) {
            // The managed copy (if any) is now stale: drop it so that the next get() reads the claimed row.
            $this->store->detach($id);
        }

        return $claimed;
    }

    #[Override]
    public function delete(DeclarationRecordId $id): void
    {
        $this->store->discard($id);
    }

    #[Override]
    public function countByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber): array
    {
        return $this->counts(
            'SELECT r.status.state AS state, COUNT(r.id) AS total FROM ' . self::ENTITY
            . ' r WHERE r.tenantId = :tenant AND r.snapshot.employerNumber = :number GROUP BY r.status.state',
            $tenantId,
            $employerNumber,
        );
    }

    /**
     * Selects the ids first (to report and detach them), then deletes under the same condition as the SELECT, state
     * included: a record claimed by another writer between the two statements is no longer in $states and survives, so
     * that the caller's re-count sees it. The managed copies of the selected records are dropped.
     */
    #[Override]
    public function deleteByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber, array $states): array
    {
        $values = array_map(static fn(DeclarationState $state): string => $state->value, $states);
        /** @var list<string> $selected */
        $selected = $this->entityManager
            ->createQuery(
                'SELECT r.id FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant'
                . ' AND r.snapshot.employerNumber = :number AND r.status.state IN (:states)'
                . ' ORDER BY r.createdAt ASC, r.id ASC',
            )
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setParameter('number', $employerNumber, PajeEmployerNumberType::NAME)
            ->setParameter('states', $values, ArrayParameterType::STRING)
            ->getSingleColumnResult();
        $ids = array_map(DeclarationRecordId::fromString(...), $selected);
        foreach ($ids as $id) {
            $this->store->detach($id);
        }
        $this->entityManager
            ->createQuery(
                'DELETE FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant'
                . ' AND r.snapshot.employerNumber = :number AND r.status.state IN (:states)',
            )
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setParameter('number', $employerNumber, PajeEmployerNumberType::NAME)
            ->setParameter('states', $values, ArrayParameterType::STRING)
            ->execute();

        return $ids;
    }

    /**
     * Number of records per state, every state present (0 when none).
     *
     * @return array<string, int>
     */
    private function counts(string $dql, TenantId $tenantId, ?PajeEmployerNumber $employerNumber): array
    {
        $counts = [];
        foreach (DeclarationState::cases() as $state) {
            $counts[$state->value] = 0;
        }
        $query = $this->entityManager->createQuery($dql)->setParameter('tenant', $tenantId, TenantIdType::NAME);
        if ($employerNumber instanceof PajeEmployerNumber) {
            $query->setParameter('number', $employerNumber, PajeEmployerNumberType::NAME);
        }
        /** @var list<array{state: DeclarationState, total: int|string}> $rows */
        $rows = $query->getResult();
        foreach ($rows as $row) {
            $counts[$row['state']->value] = \intval($row['total']);
        }

        return $counts;
    }

    /**
     * @throws InvariantViolation "declaration_record.duplicate_external_reference"
     */
    private function assertUnique(DeclarationRecord $record): void
    {
        $duplicate = $this->entityManager
            ->createQuery(
                'SELECT r.id FROM ' . self::ENTITY
                . ' r WHERE r.tenantId = :tenant AND r.id <> :id AND r.externalReference = :reference',
            )
            ->setParameter('tenant', $record->tenantId(), TenantIdType::NAME)
            ->setParameter('id', $record->id(), DeclarationRecordIdType::NAME)
            ->setParameter('reference', $record->externalReference(), ExternalReferenceType::NAME)
            ->getScalarResult();
        if ($duplicate !== []) {
            throw InvariantViolation::because(
                self::DUPLICATE_EXTERNAL_REFERENCE,
                'A declaration record already exists for this external reference.',
                ['external_reference' => $record->externalReference()->value()],
            );
        }
    }
}
