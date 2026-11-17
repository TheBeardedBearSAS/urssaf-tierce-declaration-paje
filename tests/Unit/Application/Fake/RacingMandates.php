<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Mandate repository that saves another mandate after each deletion, to simulate another process creating a mandate while an
 * employer is being erased.
 */
final readonly class RacingMandates implements MandateRepository
{
    public function __construct(
        private MandateRepository $inner,
        private Mandate $intruder,
    ) {}

    #[Override]
    public function save(Mandate $mandate): void
    {
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
        $this->inner->save($this->intruder);
    }
}
