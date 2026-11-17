<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Persistence port of the {@see Mandate} aggregate. Expected uniqueness: the id, and at most one non-cancelled mandate
 * per (tenant, employer number) (ADR 0006, 0010).
 */
interface MandateRepository
{
    /** Violation code of a save refused because another writer changed the mandate since it was read. */
    public const CONCURRENT_MODIFICATION = 'mandate.concurrent_modification';

    /**
     * Inserts or updates. Domain events are not persisted: release and publish them separately.
     *
     * Optimistic concurrency (ADR 0006, ADR 0014): a copy that another writer changed (saved or erased) since it was
     * read is refused. The copy is then stale: read the mandate again.
     *
     * @throws InvariantViolation "mandate.concurrent_modification" (see {@see self::CONCURRENT_MODIFICATION}) or
     *                            "mandate.duplicate_active"
     */
    public function save(Mandate $mandate): void;

    /**
     * @throws RecordNotFound "mandate.not_found"
     */
    public function get(MandateId $id): Mandate;

    /**
     * The non-cancelled mandate of the employer, if any (mandate guard, method 031).
     */
    public function findActive(TenantId $tenantId, PajeEmployerNumber $employerNumber): ?Mandate;

    /**
     * Every mandate of the employer, oldest registration first.
     *
     * @return list<Mandate>
     */
    public function findByEmployer(TenantId $tenantId, PajeEmployerNumber $employerNumber): array;

    /**
     * GDPR erasure (ForgetEmployer, ADR 0015): removes the mandate and, in the Doctrine bridge, its active-mandate key
     * row, so that the employer can hold a new active mandate afterwards. Nothing happens when the mandate does not
     * exist. No event.
     */
    public function delete(MandateId $id): void;
}
