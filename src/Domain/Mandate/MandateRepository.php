<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Persistence port of the {@see Mandate} aggregate. Expected uniqueness: the id, and at most one non-cancelled mandate
 * per (tenant, employer number) (ADR 0006, 0010).
 */
interface MandateRepository
{
    /**
     * Inserts or updates. Domain events are not persisted: release and publish them separately.
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
