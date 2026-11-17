<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Port: persistence of {@see DeclarationRecord}. Uniqueness expected: id; (tenant, external reference) (ADR 0006).
 *
 * One method per query or command of the use cases (ADR 0015 added the employer erasure pair), hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
interface DeclarationRecordRepository
{
    /**
     * Inserts or updates. Domain events are not persisted: release and publish them separately.
     */
    public function save(DeclarationRecord $record): void;

    /**
     * @throws RecordNotFound "declaration_record.not_found"
     */
    public function get(DeclarationRecordId $id): DeclarationRecord;

    /**
     * Idempotency of DeclareSalary: the same external reference with the same content returns the existing record.
     */
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?DeclarationRecord;

    /**
     * Least recently updated first.
     *
     * @param positive-int $limit
     *
     * @return list<DeclarationRecord>
     */
    public function findByState(TenantId $tenantId, DeclarationState $state, int $limit): array;

    /**
     * Every tenant, state submitting, last attempt at or before $olderThan (= now - stale submission delay), oldest
     * attempt first (ReconcileStaleDeclarations).
     *
     * @param positive-int $limit
     *
     * @return list<DeclarationRecord>
     */
    public function findStaleSubmissions(Instant $olderThan, int $limit): array;

    /**
     * Number of records per state (GetWorkflowOverview): state value => count, every state present (0 when none).
     *
     * @return array<string, int>
     */
    public function countByState(TenantId $tenantId): array;

    /**
     * Tenants having at least one record in the state (RequeueAfterInvalidCredentials, schedulers).
     *
     * @return list<TenantId>
     */
    public function tenantsWithState(DeclarationState $state): array;

    /**
     * Compare-and-set pending -> submitting: one more attempt, last attempt and update at now, exactly like
     * {@see DeclarationRecord::startSubmission()}. True when this call won, false when the record is missing or not
     * pending. No event is recorded: reload the record with {@see self::get()}.
     */
    public function claimForSubmission(DeclarationRecordId $id, Instant $now): bool;

    /**
     * GDPR erasure; nothing happens when the record does not exist.
     */
    public function delete(DeclarationRecordId $id): void;

    /**
     * Number of records of the employer per state (ForgetEmployer, ADR 0015): state value => count, every state present
     * (0 when none), like {@see self::countByState()} restricted to the employer number.
     *
     * @return array<string, int>
     */
    public function countByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber): array;

    /**
     * GDPR erasure (ForgetEmployer): deletes the records of the employer whose state is one of $states and returns
     * their ids, oldest creation first (ties by id). A record whose state is not in $states is kept (a submission
     * claimed concurrently survives: the caller re-counts with {@see self::countByEmployer()}). No event.
     *
     * @param non-empty-list<DeclarationState> $states
     *
     * @return list<DeclarationRecordId>
     */
    public function deleteByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber, array $states): array;
}
