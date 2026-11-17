<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Declaration record repository that saves another record after the erasure of an employer's records, to simulate another
 * process creating a record while the employer is being erased.
 *
 * One method per method of the port.
 *
 * @mago-expect lint:too-many-methods
 */
final class RacingDeclarationRecords implements DeclarationRecordRepository
{
    /** @var list<DeclarationState> */
    private array $requestedStates = [];

    public function __construct(
        private readonly DeclarationRecordRepository $inner,
        private readonly DeclarationRecord $intruder,
    ) {}

    /**
     * @return list<DeclarationState> the states of the last erasure request
     */
    public function requestedStates(): array
    {
        return $this->requestedStates;
    }

    #[Override]
    public function save(DeclarationRecord $record): void
    {
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
        return $this->inner->claimForSubmission($id, $now);
    }

    #[Override]
    public function delete(DeclarationRecordId $id): void
    {
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
        $this->requestedStates = $states;
        $deleted = $this->inner->deleteByEmployer($tenantId, $employerNumber, $states);
        $this->inner->save($this->intruder);

        return $deleted;
    }
}
